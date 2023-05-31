<?php

namespace WolfSellers\PaymentLink\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use WolfSellers\PaymentLink\Model\Payment\PaymentLink;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;

class StopSendEmailConfirmation implements ObserverInterface
{
    /** @var PaymentLinkLogger */
    protected $_logger;

    public function __construct(
        PaymentLinkLogger $paymentLinkLogger
    ) {
        $this->_logger = $paymentLinkLogger;
    }

    public function execute(Observer $observer)
    {
        try{
            $order = $observer->getEvent()->getOrder();

            $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();

            if($paymentMethod == PaymentLink::PAYMENT_METHOD_CODE){
                /*se evita que se mande el correo de nueva orden, sera enviado hasta que se realice el pago.*/
                $order->setCanSendNewEmailFlag(false);
                $order->setSendEmail(false);
                $order->save();
            }
        }
        catch (\ErrorException $e){
            $this->_logger->addError(
                "ERROR StopSendEmailConfirmation Observer: ". $e->getMessage() . $e->getTraceAsString()
            );
        }
    }
}
