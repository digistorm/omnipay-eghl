<?php

namespace Omnipay\Eghl\Message;

use GuzzleHttp\Psr7\Stream;
use Money\Currency;
use Money\Money;
use Money\Number;
use Money\Parser\DecimalMoneyParser;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Eghl Abstract Request.
 *
 * This is the parent class for all Eghl requests.
 */
abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    /**
     * Live or Test Endpoint URL.
     */
    public function getEndpointBase()
    {
        return $this->getParameter('endpointBase');
    }

    public function setEndpointBase($value)
    {
        return $this->setParameter('endpointBase', $value);
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getParameter('merchantId');
    }

    /**
     * @param $value
     *
     * @return AbstractRequest provides a fluent interface.
     */
    public function setMerchantId($value)
    {
        return $this->setParameter('merchantId', $value);
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->getParameter('password');
    }

    /**
     * @param $value
     *
     * @return AbstractRequest provides a fluent interface.
     */
    public function setPassword($value)
    {
        return $this->setParameter('password', $value);
    }

    public function setCustomer($value)
    {
        return $this->setParameter('customer', $value);
    }

    public function getCustomer()
    {
        return $this->getParameter('customer');
    }

    public function setMetadata($value)
    {
        return $this->setParameter('metadata', $value);
    }

    public function getMetadata()
    {
        return $this->getParameter('metadata');
    }

    public function setOrderId($value)
    {
        return $this->setParameter('orderId', $value);
    }

    public function getOrderId()
    {
        return $this->getParameter('orderId');
    }

    abstract protected function createResponse($data);

    /**
     * Get HTTP Method.
     *
     * This is nearly always POST but can be over-ridden in sub classes.
     *
     * @return string
     */
    public function getHttpMethod()
    {
        return 'POST';
    }

    public function getHeaders()
    {
        return ['Content-Type' => 'application/x-www-form-urlencoded'];
    }

    /**
     * {@inheritdoc}
     */
    public function sendData($data)
    {
        // Make request to eGHL eg (https://pay.e-ghl.com/ipgsg/payment.aspx)
        $data['HashValue'] = $this->generateHash($data);
        $firstResponse = $this->httpClient->request(
            $this->getHttpMethod(),
            $this->getEndpointBase(),
            $this->getHeaders(),
            http_build_query($data)
        );
        $firstParsed = $this->parseResponse($firstResponse);

        // Follow Response
        $secondResponse = $this->httpClient->request(
            $this->getHttpMethod(),
            $firstParsed['action'],
            $this->getHeaders(),
            http_build_query($firstParsed['inputs'])
        );
        $secondParsed = $this->parseResponse($secondResponse);

        // Follow Response
        $thirdResponse = $this->httpClient->request(
            $this->getHttpMethod(),
            $secondParsed['action'],
            $this->getHeaders(),
            http_build_query($secondParsed['inputs'])
        );
        $thirdParsed = $this->parseResponse($thirdResponse);
        $this->validateResponse($thirdParsed['inputs']);

        return $this->createResponse($thirdParsed['inputs']);
    }

    public function parseResponse($httpResponse)
    {
        $html = $httpResponse->getBody()->getContents();
        $formExists = preg_match('/<form name=\'frmProcessPayment\' action=\'([^\']*)\' method=\'POST\'>.*<\/form>/s', $html, $matches);
        if (!$formExists) {
            // TODO throw error
        }
        $form = $matches[0];
        $action = $matches[1];
        $inputsExist = preg_match_all('/<INPUT type=\'hidden\' name=\'([^\']*)\' value=\'([^\']*)\'>/', $form, $matches, PREG_SET_ORDER);
        if (!$inputsExist) {
            // TODO throw error
        }
        $inputs = [];
        foreach ($matches as $match) {
            // name => value
            $inputs[$match[1]] = $match[2];
        }
        return [
            'action' => $action,
            'inputs' => $inputs,
        ];
    }
    
    public function validateResponse($inputs)
    {
        // Confirm hash values
        $hashAttributes = [
            'HashValue' => [
                'TxnID',
                'ServiceID',
                'PaymentID',
                'TxnStatus',
                'Amount',
                'CurrencyCode',
                'AuthCode',
            ],
            'HashValue2' => [
                'TxnID',
                'ServiceID',
                'PaymentID',
                'TxnStatus',
                'Amount',
                'CurrencyCode',
                'AuthCode', 
                'OrderNumber',
                'Param6',
                'Param7',
            ]
        ];
        $hashKeys = [];
        foreach ($hashAttributes as $hashType => $attributes) {
            $hashKeys[$hashType] = $this->getPassword();
            foreach ($attributes as $attr) {
                if (isset($inputs[$attr])) {
                    $hashKeys[$hashType] .= $inputs[$attr];
                }
            }
            if ($inputs[$hashType] !== hash('sha256', $hashKeys[$hashType])) {
                throw new \Exception('Unable to verify ' . $hashType . ' from server');
            }
        }
    }

    /**
     * Hashing function for eGHL
     */
    public function generateHash($data)
    {
        $hashParts = [
            'ServiceID',
            'PaymentID',
            'MerchantReturnURL',
            'MerchantApprovalURL',
            'MerchantUnApprovalURL',
            'MerchantCallBackURL',
            'Amount',
            'CurrencyCode',
            'CustIP',
            'PageTimeout',
            'CardNo',
            'Token',
            'RecurringCriteria',
        ];
        $string = $this->getPassword();
        foreach ($hashParts as $hashPart) {
            if (isset($data[$hashPart])) {
                $string .= $data[$hashPart];
            }
        }
        return hash('sha256', $string);
    }

    /**
     * @param string $parameterName
     *
     * @return mixed|\Money\Money|null
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getMoney($parameterName = 'amount')
    {
        $amount = $this->getParameter($parameterName);

        if ($amount instanceof Money) {
            return $amount;
        }

        if ($amount !== null) {
            $moneyParser = new DecimalMoneyParser($this->getCurrencies());
            $currencyCode = $this->getCurrency() ?: 'MYR';
            $currency = new Currency($currencyCode);

            $number = Number::fromString($amount);

            // Check for rounding that may occur if too many significant decimal digits are supplied.
            $decimal_count = strlen($number->getFractionalPart());
            $subunit = $this->getCurrencies()->subunitFor($currency);
            if ($decimal_count > $subunit) {
                throw new InvalidRequestException('Amount precision is too high for currency.');
            }

            $money = $moneyParser->parse((string) $number, $currency->getCode());

            // Check for a negative amount.
            if (!$this->negativeAmountAllowed && $money->isNegative()) {
                throw new InvalidRequestException('A negative amount is not allowed.');
            }

            // Check for a zero amount.
            if (!$this->zeroAmountAllowed && $money->isZero()) {
                throw new InvalidRequestException('A zero amount is not allowed.');
            }

            return $money;
        }
    }

    /**
     * Filter a string value so it will not break the API request.
     *
     * @param     $string
     * @param int $maxLength
     *
     * @return bool|string
     */
    protected function filter($string, $maxLength = 50)
    {
        return substr(preg_replace('/[^a-zA-Z0-9 \-]/', '', $string), 0, $maxLength);
    }

    private function hexToBase62($hex) {
        $number = base_convert($hex, 16, 10);
        $base = 62;
        $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $remainder = bcmod($number, $base);
        $result = $charset[$remainder];
        $quotient = bcdiv($number, $base, 0);
        while ($quotient) {
            $remainder = bcmod($quotient, $base);
            $quotient = bcdiv($quotient, $base, 0);
            $result = $charset[$remainder] . $result;
        }
        return $result;

        // e30711af61b74de983ba0b5663a9123
        // qM1Tr0h83899ejeZrSJL8
    }
}
