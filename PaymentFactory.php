<?php

namespace Fullpipe\Payum\Uniteller;

use Fullpipe\Payum\Uniteller\Action\CaptureAction;
use Fullpipe\Payum\Uniteller\Action\NotifyAction;
use Fullpipe\Payum\Uniteller\Action\StatusAction;
use Fullpipe\Payum\Uniteller\Action\FillOrderDetailsAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\PaymentFactory as CorePaymentFactory;
use Payum\Core\PaymentFactoryInterface;

class PaymentFactory implements PaymentFactoryInterface
{
    /**
     * @var PaymentFactoryInterface
     */
    protected $corePaymentFactory;

    /**
     * @var array
     */
    private $defaultConfig;

    /**
     * @param array                   $defaultConfig
     * @param PaymentFactoryInterface $corePaymentFactory
     */
    public function __construct(array $defaultConfig = array(), PaymentFactoryInterface $corePaymentFactory = null)
    {
        $this->corePaymentFactory = $corePaymentFactory ?: new CorePaymentFactory();
        $this->defaultConfig = $defaultConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $config = array())
    {
        return $this->corePaymentFactory->create($this->createConfig($config));
    }

    /**
     * {@inheritDoc}
     */
    public function createConfig(array $config = array())
    {
        $config = ArrayObject::ensureArrayObject($config);
        $config->defaults($this->defaultConfig);
        $config->defaults($this->corePaymentFactory->createConfig());

        $config->defaults(array(
            'payum.factory_name' => 'uniteller',
            'payum.factory_title' => 'Uniteller',

            'payum.action.capture' => new CaptureAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.fill_order_details' => new FillOrderDetailsAction(),
        ));

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = array(
                'shop_id' => '',
                'password' => '',
                'sandbox' => true,
            );

            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = array('shop_id', 'password');

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                $unitellerConfig = array(
                    'shop_id' => $config['shop_id'],
                    'password' => $config['password'],
                    'sandbox' => $config['sandbox'],
                );

                return new Api($unitellerConfig);
            };
        }

        return (array) $config;
    }
}
