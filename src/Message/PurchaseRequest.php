<?php

namespace Omnipay\Eghl\Message;

use Omnipay\Common\Exception\InvalidRequestException;

class PurchaseRequest extends AbstractRequest
{

    public function getData()
    {
        $this->validate('transactionId', 'amount', 'currency');

        // TODO add more checks
        if (!$this->getParameter('card')) {
            throw new InvalidRequestException('You must pass a "card" parameter.');
        }

        /* @var $card \OmniPay\Common\CreditCard */
        $card = $this->getParameter('card');
        $card->validate();
        $charge = $this->getParameter('amount');
        $customer = $this->getCustomer();
        $metadata = $this->getMetadata();

        $data = [
            // Generic Details
            'ServiceID' => $this->getMerchantId(),
            'TransactionType' => 'SALE',
            'PymtMethod' => 'CC',
            'MerchantReturnURL' => 's2s',

            // Transaction Details
            'PaymentID' => $this->getTransactionId(),
            'PaymentDesc' => 'Payment for entry ' . $metadata['entry_uuid'],
            'OrderNumber' => $metadata['entry_uuid'],
            'Amount' => $charge->getAmount() / 100,
            'CurrencyCode' => $charge->getCurrency()->getCode(),
            
            // Card Details
            'CardNo' => $card->getNumber(),
            'CardHolder' => $card->getName(),
            'CardExp' => $card->getExpiryDate('Ym'),
            'CardCVV2' => $card->getCvv(),
            
            // Customer Details
            'CustName' => $customer['firstName'] . ' ' . $customer['lastName'],
            'CustEmail' => $customer['email'],
            'CustPhone' => $customer['phone'],
            'BillAddr' => $customer['address']['line_1'],
            'BillPostal' => $customer['address']['postal_code'],
            'BillCity' => $customer['address']['locality'],
            'BillRegion' => $customer['address']['administrative_area'],
            'BillCountry' => $customer['address']['country_code'],
        ];

        return $data;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        $url = parent::getEndpointBase();
        return $url;
    }

    /**
     * @return string
     */
    public function getHttpMethod()
    {
        return 'POST';
    }

    /**
     * @param $data
     *
     * @return \Omnipay\Eghl\Message\PurchaseResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
