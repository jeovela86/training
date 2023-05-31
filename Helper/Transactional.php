<?php
namespace WolfSellers\PaymentLink\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use WolfSellers\PaymentLink\Helper\LinkUtils;
use WolfSellers\OpenpaySubscriptions\Helper\Order as OpenpayOrder;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\App\Area;
use WolfSellers\PaymentLink\Helper\OrderHelper;


class Transactional extends AbstractHelper
{
    const PAYMENTLINK_CREATE_MAIL_TEMPLATE_FIELD                = 'paymentlink_notifications/paymentlink_create/template';
    const PAYMENTLINK_STORE_NOTIFICATION_EMAIL_TEMPLATE_FIELD   = 'paymentlink_notifications/paymentlink_store_notification/template';
    const PAYMENTLINK_SUCCESSFULPAYMENT_NOTIFICATION            = 'paymentlink_notifications/paymentlink_successfulpayment_notification/template';
    const PAYMENTLINK_PAYMENTDECLINED_NOTIFICATION              = 'paymentlink_notifications/paymentlink_paymentdeclined_notification/template';
    const PAYMENTLINK_CASHPAYMENT_NOTIFICATION                  = 'paymentlink_notifications/paymentlink_cash_notification/template';
    const PAYMENTLINK_UPDATE_CARD_NOTIFICATION                  = 'paymentlink_notifications/paymentlink_update_card_notification/template';
    CONST EMAIL_COPY_PATH                                       = 'paymentlink_notifications/paymentlink_create/payment_link_email_copy';

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var string
     */
    protected $temp_id;
    /**
     * @var \WolfSellers\PaymentLink\Helper\LinkUtils
     */
    protected $_linkutils;
    /**
     * @var OpenpayOrder
     */
    protected $_helperSubscription;

    /** @var Emulation */
    protected $_emulation;

    /** @var OrderHelper */
    protected $_orderHelper;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        LinkUtils $linkutils,
        OpenpayOrder $helperSubscription,
        Emulation $emulation,
        OrderHelper $orderHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->_helperSubscription = $helperSubscription;
        $this->_linkutils = $linkutils;
        $this->_emulation = $emulation;
        $this->_orderHelper = $orderHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return store configuration value of your template field that which id you set for template
     *
     * @param string $path
     * @param int $storeId
     * @return mixed
     */
    protected function getConfigValue($path, $storeId)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore()
    {
        return $this->_storeManager->getStore();
    }

    /**
     * Return template id according to store
     *
     * @return mixed
     */
    public function getTemplateId($xmlPath)
    {
        return $this->getConfigValue($xmlPath, $this->getStore()->getStoreId());
    }

    public function generateTemplate($emailTemplateVariables,$senderInfo,$receiverInfo)
    {
        $template =  $this->_transportBuilder->setTemplateIdentifier($this->temp_id)
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $this->_storeManager->getStore()->getId(),
                ]
            )
            ->setTemplateVars($emailTemplateVariables)
            ->setFrom($senderInfo)
            ->addTo($receiverInfo['email'],$receiverInfo['name']);
        return $this;
    }


    /**
     * Send Payment Link Transactional Email to Customer
     * @param $emailTemplateVariables
     * @param $receiverInfo
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function sendPaymentLinkFormURL($emailTemplateVariables,$receiverInfo)
    {
        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_CREATE_MAIL_TEMPLATE_FIELD);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($emailTemplateVariables,$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();
    }

    /**
     * Send Store Notification when customer already paid
     * @param $emailTemplateVariables
     * @param $receiverInfo
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function sendStoreNotification($emailTemplateVariables,$receiverInfo)
    {
        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_STORE_NOTIFICATION_EMAIL_TEMPLATE_FIELD);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($emailTemplateVariables,$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();
    }

    public function sendSuccessfulPaymentNotification($order, $receiverInfo)
    {
        $this->_emulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);
        $subscriptionData = $this->_helperSubscription->getSubscriptionByOrderId($order->getId());

        $deliveredInStore = $this->_linkutils->getCustomerDataByItem($order, 'delivered_in_store');
        $entrega = $deliveredInStore ? 'Entregado en tienda' : $this->_linkutils->customFormatAddres($order->getShippingAddress());

        $emailVars = [
            'customer' => [
                'id' => $this->_linkutils->getCustomerDocumentValue($order->getCustomerId()),
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'telephone' => $order->getShippingAddress()->getTelephone() ? $order->getShippingAddress()->getTelephone() : $order->getShippingAddress()->getCellphone()
            ],
            'subscription' => [
                'consecutive_number' => $subscriptionData->getConsecutiveNumber(),
                'product' => $subscriptionData->getProductName(),
                'formula' => $this->_linkutils->getFormula($order),
                'frequency' => $subscriptionData->getPeriodicity(),
                'date_delivery' => $entrega
            ]
        ];

        $transportObject = new DataObject($emailVars);

        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_SUCCESSFULPAYMENT_NOTIFICATION);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($transportObject->getData(),$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();

        $this->_emulation->stopEnvironmentEmulation();

    }

    /**
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendPaymentDeclinedNotification($order, $receiverInfo)
    {
        $subscriptionData = $this->_helperSubscription->getSubscriptionByOrderId($order->getEntityId());

        $this->_emulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);

        $deliveredInStore = $this->_linkutils->getCustomerDataByItem($order, 'delivered_in_store');
        $entrega = $deliveredInStore ? 'Entregado en tienda' : $this->_linkutils->customFormatAddres($order->getShippingAddress());

        $emailVars = [
            'customer' => [
                'id' => $this->_linkutils->getCustomerDocumentValue($order->getCustomerId()),
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'telephone' => $order->getShippingAddress()->getTelephone() ? $order->getShippingAddress()->getTelephone() : $order->getShippingAddress()->getCellphone()
            ],
            'subscription' => [
                'consecutive_number' => $subscriptionData->getConsecutiveNumber(),
                'product' => $subscriptionData->getProductName(),
                'formula' => $this->_linkutils->getFormula($order),
                'frequency' => $subscriptionData->getPeriodicity(),
                'date_delivery' => $entrega
            ]
        ];

        $transportObject = new DataObject($emailVars);

        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_PAYMENTDECLINED_NOTIFICATION);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($transportObject->getData(),$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();

        $this->_emulation->stopEnvironmentEmulation();
    }

    /**
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function cashPaymentNotification($order, $receiverInfo)
    {
        $subscriptionData = $this->_helperSubscription->getSubscriptionByOrderId($order->getId());

        $this->_emulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);

        $emailVars = [
            'customer' => [
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail()
            ],
            'subscription' => [
                'consecutive_number' => $subscriptionData->getConsecutiveNumber()
            ]
        ];

        $transportObject = new DataObject($emailVars);

        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_CASHPAYMENT_NOTIFICATION);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($transportObject->getData(),$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();

        $this->_emulation->stopEnvironmentEmulation();

    }

    /**
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendUpdateCardNotification($order, $receiverInfo)
    {
        $subscriptionData = $this->_helperSubscription->getSubscriptionByOrderId($order->getEntityId());

        $this->_emulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);
        $card = $this->_linkutils->getCustomerCards($order->getCustomerId());
        if ($card){
            $cardData =$card->serializableData;
            $tarjetaActual = $cardData['card_number'];
        }else{
            $tarjetaActual = "Sin Tarjeta";
        }

        $emailVars = [
            'customer' => [
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail()
            ],
            'card' => [
                'cc' => $tarjetaActual
            ]
        ];

        $transportObject = new DataObject($emailVars);

        $senderInfo = array(
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name',ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email',ScopeInterface::SCOPE_STORE)
        );

        $this->temp_id = $this->getTemplateId(self::PAYMENTLINK_UPDATE_CARD_NOTIFICATION);
        $this->inlineTranslation->suspend();
        $this->generateTemplate($transportObject->getData(),$senderInfo,$receiverInfo);
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();
        $this->inlineTranslation->resume();
        $this->_emulation->stopEnvironmentEmulation();
    }

    public function sendPaymentLink($customerId)
    {
        $subscription = $this->_helperSubscription->getSubscriptionOffByCustomerId($customerId,'DESC');

        $this->_emulation->startEnvironmentEmulation($subscription->getStoreId(), Area::AREA_FRONTEND, true);

        $order = $this->_orderHelper->getCustomerLastOrder($customerId);

        $templateVariables = [
            'order' => $order,
            'order_id' => $order->getId(),
            'billing' => $order->getBillingAddress(),
            'store' => $order->getStore(),
            'created_at_formatted' => $order->getCreatedAtFormatted(2),
            'order_data' => [
                'customer_name' => $order->getCustomerName(),
                'is_not_virtual' => $order->getIsNotVirtual(),
                'email_customer_note' => $order->getEmailCustomerNote(),
                'frontend_status_label' => $order->getFrontendStatusLabel()
            ],
            'customer' => [
                'name' => $order->getCustomerName(),
            ],
            'paymentlink_url' => $this->_linkutils->getPaymentLink($order->getCustomerId()),
            'subscription' => [
                'consecutive_number' => $subscription->getConsecutiveNumber()
            ],
        ];

        $transportObject = new DataObject($templateVariables);

        $this->sendPaymentLinkFormURL(
            $transportObject->getData(),
            array('email'=>$order->getCustomerEmail(),'name'=>$order->getCustomerFirstname())
        );

        $emailCopy = $this->scopeConfig->getValue(
            self::EMAIL_COPY_PATH,
            ScopeInterface::SCOPE_STORE
        );

        if($emailCopy != ''){
            $this->sendPaymentLinkFormURL(
                $transportObject->getData(),
                array('email'=>$emailCopy,'name'=>$order->getCustomerFirstname())
            );
        }

        $this->_emulation->stopEnvironmentEmulation();
    }
}
