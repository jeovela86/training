<?php

namespace WolfSellers\PaymentLink\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\WebServicesLafam\Helper\CreateShipment;
use WolfSellers\WebServicesLafam\Helper\OrderUtils;
use WolfSellers\PaymentLink\Helper\Transactional as PaymentLinkTransactional;

class ChargeConfirmationObserver implements ObserverInterface
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


    public function __construct(
        OrderUtils $orderUtils,
        PaymentLinkLogger $paymentLinkLogger,
        CreateShipment $createShipment,
        PaymentLinkTransactional $paymentLinkTransactional
    ) {
        $this->_logger = $paymentLinkLogger;
        $this->orderUtils = $orderUtils;
        $this->_createShipment = $createShipment;
        $this->_paymentLinkTransactional = $paymentLinkTransactional;
    }

    public function execute(Observer $observer)
    {
        try{
            $order = $observer->getEvent()->getOrder();
            if($this->orderUtils->isDeliveredInStore($order)){
                //Update inventory to avoid out of stock error
                $this->_createShipment->prepareShipment($order,true);
                $this->_createShipment->createShipment($order->getId());
            }

            /*envio de correo pago exitoso*/
            $this->_paymentLinkTransactional->sendSuccessfulPaymentNotification(
                $order,
                array('name' => $order->getCustomerFirstname(),'email' => $order->getCustomerEmail())
            );

        }
        catch (\Throwable $e){
            $this->_logger->addError(
                "ERROR ChargeConfirmationObserver Observer: ". $e->getMessage() . $e->getTraceAsString()
            );
        }
    }
}
