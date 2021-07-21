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

    /**
     * {@inheritdoc}
     */
    public function sendData($data)
    {
        // Make request to eGHL
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $data['HashValue'] = $this->generateHash($data);
        $httpResponse = $this->httpClient->request($this->getHttpMethod(), $this->getEndpointBase(), $headers, http_build_query($data));
        
        // Parse response as query params
        parse_str(trim($httpResponse->getBody()->getContents()), $result);
        return $this->createResponse($result);
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
            $currencyCode = $this->getCurrency() ?: 'USD';
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
}
