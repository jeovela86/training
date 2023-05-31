<?php

namespace WolfSellers\PaymentLink\Controller\Subscription;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;

class ReadyToPay extends Action
{
    /** @var CustomerSession */
    protected $_customerSession;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CustomerSession $customerSession
    )
    {
        $this->_pageFactory = $pageFactory;
        $this->_customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        if(!$this->_customerSession->isLoggedIn()){
            return $this->_redirect('customer/account/login');
        }

        return $this->_pageFactory->create();
    }
}

