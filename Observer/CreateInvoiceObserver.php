<?php

namespace WolfSellers\PaymentLink\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\WebServicesLafam\Helper\CreateShipment;
use WolfSellers\WebServicesLafam\Helper\OrderUtils;
use WolfSellers\PaymentLink\Helper\Transactional as PaymentLinkTransactional;
use WolfSellers\PaymentLink\Helper\OrderHelper as PaymentlinkHelper;

class CreateInvoiceObserver implements ObserverInterface
{
    /**
     * @var OrderUtils
     */
    protected $orderUtils;

    /** @var PaymentLinkLogger */
    protected $_logger;

    /** @var CreateShipment */
    protected $_createShipment;

    /** @var PaymentLinkTransactional */
    protected $_paymentLinkTransactional;

    /** @var PaymentlinkHelper */
    protected $_paymentLinkHelper;


    public function __construct(
        OrderUtils $orderUtils,
        PaymentLinkLogger $paymentLinkLogger,
        CreateShipment $createShipment,
        PaymentLinkTransactional $paymentLinkTransactional,
        PaymentlinkHelper $paymentLinkHelper
    ) {
        $this->_logger = $paymentLinkLogger;
        $this->orderUtils = $orderUtils;
        $this->_createShipment = $createShipment;
        $this->_paymentLinkTransactional = $paymentLinkTransactional;
        $this->_paymentLinkHelper = $paymentLinkHelper;
    }

    public function execute(Observer $observer)
    {
        try{
            $order = $observer->getEvent()->getOrder();

            $this->_paymentLinkHelper->createInvoice($order->getId());
        }
        catch (\Throwable $e){
            $this->_logger->addError(
                "ERROR CreateInvoiceObserver Observer: ". $e->getMessage() . $e->getTraceAsString()
            );
        }
    }
}
