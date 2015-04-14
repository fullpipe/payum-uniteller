<?php

namespace Fullpipe\Payum\Uniteller\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\FillOrderDetails;
use Payum\Core\Bridge\Spl\ArrayObject;
use Fullpipe\Payum\Uniteller\Api;

class FillOrderDetailsAction implements ActionInterface, ApiAwareInterface
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * {@inheritDoc}
     */
    public function setApi($api)
    {
        if (false == $api instanceof Api) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    /**
     * {@inheritDoc}
     *
     * @param FillOrderDetails $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $order = $request->getOrder();
        $details = ArrayObject::ensureArrayObject($order->getDetails());

        $details->defaults(array(
            'Lifetime' => Api::DEFAULT_PAYMENT_FORM_LIFETIME,
            'OrderLifetime' => Api::DEFAULT_ORDER_LIFETIME,
            'Language' => Api::PAYMENT_PAGE_LANGUAGE_RU,
            'MeanType' => Api::MEAN_TYPE_ANY,
            'EMoneyType' => Api::EMONEY_TYPE_ANY,
        ));

        if ($this->api->isSandbox()) {
            unset($details['OrderLifetime']);
            unset($details['MeanType']);
            unset($details['EMoneyType']);
        }

        $details['Order_IDP'] = $this->api->validateOrderNumber($order->getNumber());
        $details['Subtotal_P'] = ((float) $order->getTotalAmount())/100;
        $details['Currency'] = $this->api->validateOrderCurrency($order->getCurrencyCode());
        $details['Comment'] = $order->getDescription();
        $details['Customer_IDP'] = $order->getClientId();
        $details['Email'] = $order->getClientEmail();

        $details->validateNotEmpty('Order_IDP', 'Subtotal_P', 'Currency');

        $order->setDetails($details);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return $request instanceof FillOrderDetails;
    }
}
