<?php

namespace WolfSellers\PaymentLink\Cron;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use WolfSellers\PaymentLink\Helper\sendNotificationUpdateCard;
use WolfSellers\Subscriptions\Model\SubscriptionsRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\WebServicesLafam\Helper\OrderUtils;
use Magento\Store\Model\App\Emulation;

class DigitalCardRecurrenceNotification
{
    /**
     * @var sendNotificationUpdateCard
     */
    protected $_notification;
    /**
     * @var SubscriptionsRepository
     */
    protected $_subscriptionsRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $_searchCriteria;
    /**
     * @var PaymentLinkLogger
     */
    private $_paymentLinkLogger;

    /** @var OrderUtils */
    protected $_orderUtils;

    protected $_emulation;

    public function __construct(
        sendNotificationUpdateCard $notification,
        SubscriptionsRepository $subscriptionsRepository,
        SearchCriteriaBuilder $searchCriteria,
        PaymentLinkLogger $paymentLinkLogger,
        OrderUtils $orderUtils,
        Emulation $emulation
    ){
        $this->_notification = $notification;
        $this->_orderUtils = $orderUtils;
        $this->_subscriptionsRepository = $subscriptionsRepository;
        $this->_searchCriteria = $searchCriteria;
        $this->_paymentLinkLogger = $paymentLinkLogger;
        $this->_emulation = $emulation;
    }

    /**
     * @throws LocalizedException
     */
    public function execute() {
        try {
            $this->_paymentLinkLogger->addInfo("=== EXECUTE CRONJOB DIGITAL_CARD_UPDATE_RECURRENCE_NOTIFICATION ===");

            $days = 1;
            $currentTime = $this->_orderUtils->getDateByStoreId();
            $dateLimit = date("Y-m-d H:i:s", strtotime($currentTime . " +" . $days . " days"));

            $searchCriteria = $this->_searchCriteria
                ->addFilter('status', '1')
                ->addFilter('is_digital', '1')
                ->addFilter('payment_date',$dateLimit,'lteq')
                ->addFilter('is_card_updated', 0, 'eq')
                ->addFilter('update_card_notification_sent', 0, 'eq')
                ->addFilter('payment_method', array('paymentlink','openpay_cards'), 'in')
                ->create();

            $subscriptions = $this->_subscriptionsRepository->getList($searchCriteria);

            if ($subscriptions->getTotalCount()) foreach ($subscriptions->getItems() as $subscription) {
                try {
                    $this->_emulation->startEnvironmentEmulation($subscription->getStoreId(), Area::AREA_FRONTEND, true);
                    $this->_notification->send($subscription->getCustomerId());
                    $subscription->setUpdateCardNotificationSent(true);
                    $this->_subscriptionsRepository->save($subscription);
                    $this->_emulation->stopEnvironmentEmulation();
                } catch (Throwable $error) {
                    $this->_paymentLinkLogger->addError("ERROR CRONJOB DIGITAL_CARD_UPDATE_RECURRENCE_NOTIFICATION: " . $error->getMessage());
                }
            }
        } catch (Throwable $error) {
            $this->_paymentLinkLogger->addError("=== ERROR EXECUTE CRONJOB DIGITAL_CARD_UPDATE_RECURRENCE_NOTIFICATION ===");
        }
    }
}
