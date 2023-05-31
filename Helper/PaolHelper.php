<?php
namespace WolfSellers\PaymentLink\Helper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\WebServicesLafam\Helper\OrderUtils;
use WolfSellers\WebServicesLafam\Plugin\WebServices;
use WolfSellers\OpenpaySubscriptions\Helper\Order as SubscriptionHelper;

class PaolHelper extends AbstractHelper
{
    /** @var WebServices */
    protected $_webServices;

    /** @var PaymentLinkLogger  */
    protected $_paymentLinkLogger;

    /** @var OrderUtils */
    protected $_orderUtils;

    /** @var SubscriptionHelper */
    protected $_subscriptionHelper;

    public function __construct(
        Context $context,
        WebServices $webServices,
        PaymentLinkLogger $paymentLinkLogger,
        OrderUtils $orderUtils,
        SubscriptionHelper $subscriptionHelper
    ) {
        parent::__construct($context);
        $this->_webServices = $webServices;
        $this->_paymentLinkLogger = $paymentLinkLogger;
        $this->_orderUtils = $orderUtils;
        $this->_subscriptionHelper = $subscriptionHelper;
    }

    public function sendToPaol(\Magento\Sales\Model\Order $order){
        try{
            $this->_webServices->sendCustomer($order);
            $this->_webServices->sendShippingAddress($order);
            $this->_webServices->sendBillingAddress($order);

            if ($this->_orderUtils->isBackOrder($order) && !$this->_orderUtils->isDeliveredInStore($order)) {
                $this->_webServices->sendPreOrder($order);
                //agendar envio de orden posteriormente
                $this->_webServices->scheduleOrder($order,true);

                // set lafam_preorder_sent status
                $order->setStatus('lafam_preorder_sent');
                $order->save();
            } else {

                // Si tiene stock se manda orden directamente
                $this->_webServices->scheduleOrder($order,false);

                // set lafam_order_sent status
                $order->setStatus('lafam_order_sent');
                $order->save();

                $subscription = $this->_subscriptionHelper->getSubscriptionByOrderId($order->getId());
                /* validacion para la primera entrega*/
                if($this->_orderUtils->getCurrentOrderRecurrenceBySubscriptionId($subscription->getSubscriptionsId()) == 1){
                    /*actualiza las fechas para la siguiente orden*/
                    $periodicity = $subscription->getPeriodicity();
                    $currentDate = $this->_orderUtils->getDateByStoreId();
                    $currentDate = date("Y-m-d H:i:s",strtotime($currentDate."+ ".$periodicity." month"));
                    $this->_orderUtils->updateDeliveryDateSubscription($subscription->getSubscriptionsId(), $currentDate);
                }
            }
        }catch (\Throwable $error) {
            $this->_paymentLinkLogger->addError(
                "ERROR PaymentHelper sendToPaol: ". $error->getMessage() . $error->getTraceAsString()
            );
        }
    }
}
