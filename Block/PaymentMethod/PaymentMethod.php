<?php

namespace WolfSellers\PaymentLink\Block\PaymentMethod;
use WolfSellers\OpenpaySubscriptions\Helper\OpenpayAPI;
use Magento\Customer\Model\Session;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;

class PaymentMethod extends \Magento\Framework\View\Element\Template
{
    /** @var Session  */
    protected $_session;

    /** @var PaymentLinkLogger  */
    protected $_paymentLinkLogger;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Session $session,
        PaymentLinkLogger $paymentLinkLogger
    )
    {
        parent::__construct($context);
        $this->_session = $session;
        $this->_paymentLinkLogger = $paymentLinkLogger;
    }

    /**
     * Get form action URL for POST booking request
     *
     * @return string
     */
    public function getFormAction()
    {
        return '/paymentlink/paymentmethod/update';
    }

    public function getYearFinal()
    {
        return date('Y') + 11;
    }

    /**
     * @return false|mixed
     */
    public function getCustomerCards()
    {
        try{
            $customerId = $this->_session->getCustomerId();
            return false;
        } catch (\Throwable $error){
            $this->_paymentLinkLogger->addError(
                "getCustomerCards method: ".
                $error->getMessage().
                $error->getTraceAsString()
            );
            return false;
        }
    }
}
