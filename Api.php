<?php

namespace Fullpipe\Payum\Uniteller;

use Payum\Core\Exception\LogicException;

class Api
{
    const ORDER_NUMBER_MAXIMUM_LENGHT = 127;
    const ORDER_NUMBER_MINIMUM_LENGTH = 1;

    const DEFAULT_PAYMENT_FORM_LIFETIME = 300;
    const DEFAULT_ORDER_LIFETIME = 10800;

    const MEAN_TYPE_ANY = 0;
    const MEAN_TYPE_VISA = 1;
    const MEAN_TYPE_MASTERCARD = 2;
    const MEAN_TYPE_DINERSCLUB = 3;
    const MEAN_TYPE_JCB = 4;
    const MEAN_TYPE_AMERICANEXPRESS = 5;

    const EMONEY_TYPE_ANY = 0;
    const EMONEY_TYPE_YANDEX = 1;
    const EMONEY_TYPE_RBK_MONEY = 2;
    const EMONEY_TYPE_MONEYMAIL = 3;
    const EMONEY_TYPE_WEBCREDS = 4;
    const EMONEY_TYPE_PLATEZH_RU = 6;
    const EMONEY_TYPE_MAIL_RU = 7;
    const EMONEY_TYPE_MOBILE_MEGAFON = 8;
    const EMONEY_TYPE_MOBILE_MTS = 9;
    const EMONEY_TYPE_MOBILE_BEELINE = 10;
    const EMONEY_TYPE_PAYPAL = 11;
    const EMONEY_TYPE_VKONTAKTE = 12;
    const EMONEY_TYPE_EUROSET = 13;
    const EMONEY_TYPE_YOTA = 14;
    const EMONEY_TYPE_QIWI = 15;
    const EMONEY_TYPE_PLATFON = 16;
    const EMONEY_TYPE_MONEYBOOKERS = 17;
    const EMONEY_TYPE_WEBMONEY_WMR = 29;

    const PAYMENT_PAGE_LANGUAGE_RU = 'ru';
    const PAYMENT_PAGE_LANGUAGE_EN = 'en';

    const PAYMENT_STATUS_AUTHORIZED = 'authorized';
    const PAYMENT_STATUS_NOT_AUTHORIZED = 'not authorized';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_CANCELED = 'canceled';
    const PAYMENT_STATUS_WAITING = 'waiting';

    const CURRENCY_RUB = 'RUB';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_AZN = 'AZN';
    const CURRENCY_KZT = 'KZT';

    /**
     * @var array
     */
    private $config;

    /**
     * Constructor.
     *
     * @param array $config
     *
     * @todo  validate config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getPaymentPageUrl()
    {
        return $this->isSandbox()
            ? 'https://test.wpay.uniteller.ru/pay/'
            : 'https://wpay.uniteller.ru/pay/'
            ;
    }

    public function getShopId()
    {
        return $this->config['shop_id'];
    }

    private function getPassword()
    {
        return $this->config['password'];
    }

    public function isSandbox()
    {
        return $this->config['sandbox'];
    }

    /**
     * Build order signature from order details.
     * uppercase(md5(
     *     md5(Shop_IDP) + '&' +
     *     md5(Order_IDP) + '&' +
     *     md5(Subtotal_P) + '&' +
     *     md5(MeanType) +'&' +
     *     md5(EMoneyType) + '&' +
     *     md5(Lifetime) + '&' +
     *     md5(Customer_IDP) + '&' +
     *     md5(Card_IDP) + '&' +
     *     md5(IData) +'&' +
     *     md5(PT_Code) + '&' +
     *     md5(OrderLifetime) + '&' + //only if not in sandbox
     *     md5(password))).
     *
     * @param array $params
     *
     * @return string
     */
    public function sing(array $params)
    {
        $singParams = array(
            'Shop_IDP' => $this->getShopId(),
            'Order_IDP' => '',
            'Subtotal_P' => '',
            'MeanType' => '',
            'EMoneyType' => '',
            'Lifetime' => '',
            'Customer_IDP' => '',
            'Card_IDP' => '',
            'IDat' => '',
            'PT_Code' => '',
            'OrderLifetime' => '',
            'password' => $this->getPassword(),
        );

        if ($this->isSandbox()) {
            unset($singParams['OrderLifetime']);
        }

        $params = array_intersect_key($params, $singParams);
        $singParams = array_merge($singParams, $params);
        $md5SingParams = array_map('md5', $singParams);

        return strtoupper(md5(implode('&', $md5SingParams)));
    }

    /**
     * Validate order ID.
     *
     * @param string $orderNumber
     *
     * @return string
     *
     * @throws LogicException when order id is invalid
     */
    public function validateOrderNumber($orderNumber)
    {
        if (strlen($orderNumber) > self::ORDER_NUMBER_MAXIMUM_LENGHT) {
            throw new LogicException(sprintf(
                "Order number can't have more then %s characters",
                self::ORDER_NUMBER_MAXIMUM_LENGHT
            ));
        }

        if (!preg_match('/^[\w\d]{1,'.self::ORDER_NUMBER_MAXIMUM_LENGHT.'}$/i', $orderNumber)) {
            throw new LogicException('The payment gateway doesn\'t allow order numbers with this format.');
        }

        return $orderNumber;
    }

    /**
     * Validate currency code.
     *
     * @param string $currencyCode
     *
     * @return string
     */
    public function validateOrderCurrency($currencyCode)
    {
        if (!in_array($currencyCode, self::valideCurrencyCodes())) {
            throw new LogicException(sprintf(
                "Currency code '%s' does not supported by Uniteller",
                $currencyCode
            ));
        }

        return $currencyCode;
    }

    /**
     * Validate notification signature.
     *
     * @param array $params
     *
     * @return boolean
     */
    public function validateNotificationSignature(array $params)
    {
        if (!isset($params['Signature']) || !isset($params['Order_ID']) || !isset($params['Status'])) {
            return false;
        }

        $signatureParams = array(
            'Order_ID' => $params['Order_ID'],
            'Status' => $params['Status'],
            'password' => $this->getPassword(),
        );

        $signature = strtoupper(md5(implode('', $signatureParams)));

        return $signature == $params['Signature'];
    }

    /**
     * Supported currencies.
     *
     * @return array
     */
    public static function valideCurrencyCodes()
    {
        return array(
            self::CURRENCY_RUB,
            self::CURRENCY_UAH,
            self::CURRENCY_AZN,
            self::CURRENCY_KZT,
        );
    }
}
