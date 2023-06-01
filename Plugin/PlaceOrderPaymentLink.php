<?php

namespace WolfSellers\PaymentLink\Plugin;
use Magento\Framework\DataObject;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\PaymentLink\Model\Payment\PaymentLink;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PlaceOrderPaymentLink
{
    CONST EMAIL_COPY_PATH = 'paymentlink_notifications/paymentlink_create/payment_link_email_copy';

    /** @var OrderRepositoryInterface  */
    protected $orderRepository;

    protected $_encryptor;

    /** @var PaymentLinkLogger  */
    protected $_logger;

    /** @var ScopeConfigInterface */
    protected $_scopeConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        EncryptorInterface $encryptor,
        PaymentLinkLogger $logger,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->_logger = $logger;
        $this->_encryptor = $encryptor;
        $this->orderRepository = $orderRepository;
        $this->_scopeConfig = $scopeConfig;
    }

    public function afterPlace( OrderManagementInterface $subject,
                                OrderInterface $result){

        $order = $result;

        try {
            if($order->getPayment()->getMethod() == PaymentLink::PAYMENT_METHOD_CODE)
            {
                //$this->_transactionalHelper->sendPaymentLink($order->getCustomerId());
            }

        } catch (\Throwable $error) {
            $this->_logger->addError(
                "ERROR AFTERPLACE PLUGIN ". $error->getMessage() . $error->getTraceAsString()
            );
        }
        return $result;
    }
}

