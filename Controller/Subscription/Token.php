<?php

namespace WolfSellers\PaymentLink\Controller\Subscription;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use WolfSellers\PaymentLink\Helper\LinkUtils;
use WolfSellers\PaymentLink\Helper\OrderHelper;

class Token extends Action
{
    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /** @var LinkUtils */
    protected $_linkUtils;

    /** @var OrderHelper */
    protected $_orderHelper;

    /** @var ManagerInterface  */
    protected $_messageManager;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param LinkUtils $linkUtils
     * @param OrderHelper $orderHelper
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        LinkUtils $linkUtils,
        OrderHelper $orderHelper,
        ManagerInterface $messageManager
    ) {
        $this->_linkUtils = $linkUtils;
        $this->_pageFactory = $pageFactory;
        $this->_orderHelper = $orderHelper;
        $this->_messageManager = $messageManager;
        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->_pageFactory->create();
        $layout = $resultPage->getLayout();

        $customerEncrypted = urldecode($this->_request->getParam('d'));
        $customerEncrypted = str_replace(" ","+",$customerEncrypted);
        $customerId = $this->_linkUtils->decryptCustomerId($customerEncrypted);
        $order = $this->_orderHelper->getCustomerLastOrder(intval($customerId));
        $validateValidity = $this->_orderHelper->validateValidity(intval($customerId));

        if(!$order || !$validateValidity){
            $layout->unsetElement('paymentlink_form_page');
        }else{
            $layout->unsetElement('expired_link');
        }

        return $resultPage;
    }
}
