<?php

namespace Fullpipe\Payum\Uniteller\Action;

use Fullpipe\Payum\Uniteller\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (null === $model['Signature']) {
            $request->markNew();

            return;
        }

        if ($model['Signature'] && null === $model['Status']) {
            $request->markPending();

            return;
        }

        switch ($model['Status']) {
            case Api::PAYMENT_STATUS_AUTHORIZED:
                $request->markAuthorized();
                break;
            case Api::PAYMENT_STATUS_NOT_AUTHORIZED:
                $request->markFailed();
                break;
            case Api::PAYMENT_STATUS_PAID:
                $request->markCaptured();
                break;
            case Api::PAYMENT_STATUS_CANCELED:
                $request->markCanceled();
                break;
            case Api::PAYMENT_STATUS_WAITING:
                $request->markPending();
                break;
            default:
                $request->markUnknown();
                break;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
