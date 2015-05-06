# Uniteller payment gateway for [payum](http://payum.org/)

## Instalation (with symfony2 payum bundle)
add to your composer json
```json
{
    "require": {
        "payum/payum-bundle": "0.14.*",
        "fullpipe/payum-uniteller": "dev-master"
    }
}
```

Add UnitellerPaymentFactory to payum:
```php
<?php

// src/Acme/PaymentBundle/AcmePaymentBundle.php

namespace Acme\PaymentBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Fullpipe\Payum\Uniteller\Bridge\Symfony\UnitellerPaymentFactory;

class AcmePaymentBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('payum');
        $extension->addPaymentFactory(new UnitellerPaymentFactory());
    }
}
```

Since Uniteller does not supports callback urls.
You will require to implement `notifyAction`

```php
<?php
// /src/Acme/PaymentBundle/Controller/PaymentController.php

namespace Acme\PaymentBundle\Controller;

use Payum\Bundle\PayumBundle\Controller\PayumController;
use Symfony\Component\HttpFoundation\Request;
use Payum\Core\Request\Notify;
use Payum\Core\Request\GetHumanStatus;

class PaymentController extends PayumController
{
    public function notifyAction(Request $request)
    {
        $gateway = $this->getPayum()->getPayment('uniteller');
        $payment = $this->getPayum()
            ->getStorage('Acme\PaymentBundle\Entity\Payment')
            ->findBy(array(
                'number' => $request->get('Order_ID'),
            ));

        if ($reply = $gateway->execute(new Notify($payment), true)) {
            if ($reply instanceof HttpResponse) {
                $gateway->execute($status = new GetHumanStatus($payment));

                if ($status->isCaptured() || $status->isAuthorized()) {
                    // Payment is done
                    // Notify your app here
                }

                throw $reply;
            }

            throw new \LogicException('Unsupported reply', null, $reply);
        }
        return new Response('', 204);
    }
}
```
and you in routing.yml
```yaml
acme_payment_notify:
    path:     /payment_notify
    defaults: { _controller: AcmePaymentBundle:Payment:notify }
```
add `http://example.com/payment_notify` to 
`https://lk.uniteller.ru/#/ecshop/settings/????`

## Configuration (using symfony2 payum bundle)
```yaml
payum:
    security:
        token_storage:
            Acme\PaymentBundle\Entity\PaymentToken: { doctrine: orm }
    storages:
        Acme\PaymentBundle\Entity\Payment: { doctrine: orm }
    payments:
        ...
        uniteller_gateway:
            uniteller:
                shop_id: 1234567890-1234
                password: SECRET_PASSWORD
                sandbox: true
        ...
```

## Usage
```php
<?php

namespace Acme\PaymentBundle\Controller;

use Payum\Bundle\PayumBundle\Controller\PayumController;
use Symfony\Component\HttpFoundation\Request;
use Payum\Core\Request\Notify;
use Payum\Core\Request\GetHumanStatus;
use Fullpipe\Payum\Uniteller\Api;

class PaymentController extends PayumController
{
    public function prepareAction()
    {
        $order = ...;
        $user = ...;

        $paymentName = 'uniteller_gateway';

        $storage = $this->get('payum')
            ->getStorage('Acme\PaymentBundle\Entity\Payment');

        $payment = $storage->create();
        $payment->setNumber($order->getId());
        $payment->setCurrencyCode(Api::CURRENCY_RUB);
        $payment->setTotalAmount($order->getTotalAmount());
        // $payment->setTotalAmount(14025); // => 140 руб. 25 копеек

        //Optional
        $payment->setDescription('DESCRIPTION');
        $payment->setClientId($user->getId());
        $payment->setClientEmail($user->getEmail());
        $payment->setDetails(array(
            'FirstName' => $user->getFirstName(),
            'LastName' => $user->getLastName(),
            'MeanType' => Api::MEAN_TYPE_ANY,
            'EMoneyType' => Api::EMONEY_TYPE_YANDEX,
        ));

        $storage->update($payment);

        $captureToken = $this->get('payum.security.token_factory')
            ->createCaptureToken(
                $paymentName,
                $payment,
                'acme_payment_done'
            );

        return $this->redirect($captureToken->getTargetUrl());
    }

    public function doneAction(Request $request)
    {
        $token = $this->getHttpRequestVerifier()->verify($request);
        $gateway = $this->getPayum()->getPayment($token->getPaymentName());

        $gateway->execute($status = new GetHumanStatus($token));
        $payment = $status->getFirstModel();

        //update order status here
        // if ($status->isCaptured()) {
        //     $order->setPaid(true);
        // }

        $this->get('payum.security.http_request_verifier')->invalidate($token);

        return $this->redirect($this->generateUrl('acme_thank_you_page'));
    }
}
```

