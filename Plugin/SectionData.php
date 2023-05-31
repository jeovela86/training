<?php
namespace WolfSellers\PaymentLink\Plugin;

use WolfSellers\PaymentLink\Helper\OrderHelper;
use Magento\Customer\Model\Session as CustomerSession;

class SectionData
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;


    protected $_orderHelper;
    public function __construct(
        CustomerSession $checkoutSession,
        OrderHelper $orderHelper
    )
    {
        $this->_orderHelper = $orderHelper;
        $this->customerSession = $checkoutSession;
    }

    public function afterGetSectionData(\Magento\Customer\CustomerData\Customer $subject, $result)
    {
        try{
            $notificationCVV = $this->_orderHelper->updateCVV();
            $notificationUpdateCard = $this->_orderHelper->updateCard();

            if($notificationCVV || $notificationUpdateCard ){
                $result['notification_number'] = 1;
            }

            if($notificationUpdateCard){
                $result['notification_update_card'] = 1;
            }

        }catch (\Exception $exception){

        }
        return $result;
    }
}
