<?php

namespace WolfSellers\PaymentLink\Controller\PaymentMethod;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class change extends \Magento\Framework\App\Action\Action {

    /**
     * @var PageFactory
     */
    protected $_pageFactory;
    /**
     * @var Session
     */
    protected $_customerSession;

    public function __construct(
        PageFactory $pageFactory,
        Session $customerSession,
        Context $context
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute() {
        $resultPage = $this->_pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Payment Method'));

        if (!$this->_customerSession->isLoggedIn()){
            return $this->_redirect('customer/account/');
        }

        return  $resultPage;
    }
}
