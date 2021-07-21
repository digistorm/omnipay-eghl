<?php

namespace Omnipay\Eghl\Message;

use Omnipay\Common\Exception\InvalidRequestException;

class PurchaseRequest extends AbstractRequest
{

    public function getData()
    {
        $this->validate('transactionId', 'amount', 'currency');

        if (!$this->getParameter('card')) {
            throw new InvalidRequestException('You must pass a "card" parameter.');
        }

        /* @var $card \OmniPay\Common\CreditCard */
        $card = $this->getParameter('card');
        $card->validate();

        $charge = $this->getParameter('amount');

        $data = [
            // Generic Details
            'ServiceID' => $this->getMerchantId(),
            'TransactionType' => 'SALE',
            'PymtMethod' => 'CC',
            'MerchantReturnURL' => 's2s',

            // Transaction Details
            'PaymentID' => 'ds' . time(), // TODO Replace
            'PaymentDesc' => 'TestOrder', // TODO Replace
            'OrderNumber' => 'dso' . time(), // TODO Replace
            'Amount' => $charge->getAmount() / 100,
            'CurrencyCode' => $charge->getCurrency()->getCode(),

            // Card Details
            'CardNo' => $card->getNumber(),
            'CardHolder' => $card->getName(),
            'CardExp' => $card->getExpiryDate('Ym'),
            'CardCVV2' => $card->getCvv(),

            // Customer Details
            'BillAddr' => '-', // TODO get customer details
            'BillPostal' => '-',
            'BillCity' => '-',
            'BillRegion' => '-',
            'BillCountry' => '-',
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
