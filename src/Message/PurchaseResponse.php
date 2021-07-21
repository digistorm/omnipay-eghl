<?php

namespace Omnipay\Eghl\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

class PurchaseResponse extends AbstractResponse
{

    const PAYMENT_STATUS_SUCCESS = '0';
    const PAYMENT_STATUS_FAILED = '1';
    const PAYMENT_STATUS_PENDING = '2';


    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, json_decode($data, true));
    }

    public function isSuccessful()
    {
        if (!isset($this->data['TxnStatus']) !== PurchaseResponse::PAYMENT_STATUS_SUCCESS) {
            return false;
        }
        return true;
    }

    /**
     * Get the error message from the response.
     *
     * Returns null if the request was successful.
     *
     * @return string|null
     */
    public function getMessage()
    {
        if ($this->isSuccessful()) {
            return null;
        }
        if (isset($this->data['TxnMessage'])) {
            return $this->data['TxnMessage'];
        }
        return 'Unknown Error';
    }

    /**
     * Gateway Reference
     *
     * @return null|string A reference provided by the gateway to represent this transaction
     */
    public function getTransactionReference()
    {
        if (isset($this->data['TxnID'])) {
            return $this->data['TxnID'];
        }
    }
}
