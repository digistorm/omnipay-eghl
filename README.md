# Omnipay: eGHL (server-to-server)

**eGHL (server-to-server) driver for the Omnipay PHP payment processing library**

Currently only supports purchases with one available method:

- purchase()

## Usage

```php
<?php
use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;

// Create a gateway for the Mpgs Gateway
// (routes to GatewayFactory::create)
/* @var \Omnipay\Mpgs\Gateway $gateway */
$gateway = Omnipay::create('eGHL');

$gateway->setTestMode(true);
$gateway->setEndpointBase('https://test-gateway.mastercard.com');
$gateway->setMerchantId('merchantIdValue');
$gateway->setPassword('passwordValue');


// Charge using a card
/* @var \Omnipay\Mpgs\Message\PurchaseResponse $response */
$response = $gateway->purchase([
    'card' => new CreditCard([
        'number' => '5111111111111118',
        'cvv' => '100',
        'expiryMonth' => '05',
        'expiryYear' => '2021',
        'firstName' => 'John',
        'lastName' => 'Doe',
    ]),
    'amount' => '50.00',
    'currency' => 'AUD',
    'description' => 'Merchant Reference',
])->send();
```
