<?php

namespace WolfSellers\PaymentLink\Plugin;
use Magento\Framework\DataObject;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use WolfSellers\PaymentLink\Helper\Transactional;
use Magento\Framework\Encryption\EncryptorInterface;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\PaymentLink\Model\Payment\PaymentLink;
use WolfSellers\PaymentLink\Helper\LinkUtils;
use WolfSellers\OpenpaySubscriptions\Helper\Order as SubscriptionHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PlaceOrderPaymentLink
{
    CONST EMAIL_COPY_PATH = 'paymentlink_notifications/paymentlink_create/payment_link_email_copy';

    /** @var OrderRepositoryInterface  */
    protected $orderRepository;

    /** @var Transactional */
    protected $_transactionalHelper;

    protected $_encryptor;

    /** @var LinkUtils */
    protected $_linkUtils;

    /** @var PaymentLinkLogger  */
    protected $_logger;

    /** @var SubscriptionHelper */
    protected $_subscriptionHelper;

    /** @var ScopeConfigInterface */
    protected $_scopeConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Transactional $transactionalHelper,
        EncryptorInterface $encryptor,
        LinkUtils $linkUtils,
        PaymentLinkLogger $logger,
        SubscriptionHelper $subscriptionHelper,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->_subscriptionHelper = $subscriptionHelper;
        $this->_logger = $logger;
        $this->_linkUtils = $linkUtils;
        $this->_encryptor = $encryptor;
        $this->orderRepository = $orderRepository;
        $this->_transactionalHelper = $transactionalHelper;
        $this->_scopeConfig = $scopeConfig;
    }

    public function afterPlace( OrderManagementInterface $subject,
                                OrderInterface $result){

        $order = $result;

        try {
            if($order->getPayment()->getMethod() == PaymentLink::PAYMENT_METHOD_CODE)
            {
                $this->_transactionalHelper->sendPaymentLink($order->getCustomerId());
            }

        } catch (\Throwable $error) {
            $this->_logger->addError(
                "ERROR AFTERPLACE PLUGIN ". $error->getMessage() . $error->getTraceAsString()
            );
        }
        return $result;
    }
}

