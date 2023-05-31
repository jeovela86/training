<?php

namespace WolfSellers\PaymentLink\Block\Form;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use WolfSellers\PaymentLink\Helper\LinkUtils;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use WolfSellers\OpenpaySubscriptions\Helper\Order;
use WolfSellers\OpenpaySubscriptions\Helper\OpenpayAPI;

class Openpay extends Template
{
    CONST SANDBOX_MERCHANT_ID_PATH  = 'payment/openpay_cards/sandbox_merchant_id';
    CONST LIVE_MERCHANT_ID_PATH     = 'payment/openpay_cards/live_merchant_id';
    CONST SANDBOX_PK_PATH           = 'payment/openpay_cards/sandbox_pk';
    CONST LIVE_PK_PATH              = 'payment/openpay_cards/live_pk';
    CONST IS_SANDBOX_PATH           = 'payment/openpay_cards/is_sandbox';

    /** string */
    protected $_customerId;

    /**
     * @var LinkUtils
     */
    protected $_linkUtils;

    /**
     * @var Session
     */
    protected $_customerSession;

    /** string */
    protected $_action;

    /**
     * @var Order
     */
    protected $_helperSubscription;

    /** @var OpenpayAPI */
    protected $_openpayAPI;

    public function __construct(
        Context $context,
        LinkUtils $linkUtils,
        Session $customerSession,
        Order $helperSubscription,
        OpenpayAPI $openpayAPI,
        array $data = []
    ) {
        parent::__construct($context,$data);

        $this->_linkUtils = $linkUtils;
        $this->_customerSession = $customerSession;
        $this->_helperSubscription = $helperSubscription;
        $this->_openpayAPI = $openpayAPI;

        $customerId = $this->_request->getParam('d');

        if ($customerId) {
            $this->setCustomerId($customerId);
        } else {
            /*si el formulario se abre en mi cuenta obtiene el customerId*/
            if($this->_customerSession->isLoggedIn()){
                $customerId = $this->_customerSession->getCustomerId();
                $this->setCustomerId($this->_linkUtils->encryptCustomerId($customerId));
            }
        }

        $action = $this->_request->getParam('process');
        if(!$action){
            $this->setAction($data['process'] ?? ''); //layout argument parameter
        }else{
            $this->setAction($action); // get parameter
        }
    }

    /**
     * @return mixed
     */
    public function getMerchantId(){
        if($this->isSandBoxMode()){
            return $this->getConfig(self::SANDBOX_MERCHANT_ID_PATH);
        }else{
            return $this->getConfig(self::LIVE_MERCHANT_ID_PATH);
        }
    }

    /**
     * @return mixed
     */
    public function getPublicKey(){
        if($this->isSandBoxMode()){
            return $this->getConfig(self::SANDBOX_PK_PATH);
        }else{
            return $this->getConfig(self::LIVE_PK_PATH);
        }
    }

    /**
     * @return mixed
     */
    public function isSandBoxMode(){
        return boolval($this->getConfig(self::IS_SANDBOX_PATH));
    }

    /**
     * @param $path
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->_customerId;
    }

    /**
     * @param mixed $customerId
     */
    public function setCustomerId($customerId): void
    {
        $this->_customerId = urlencode($customerId);
    }

    /**
     * @param $action
     */
    public function setAction($action){
        $this->_action = $action;
    }

    /**
     * @return mixed
     */
    public function getAction(){
        return $this->_action;
    }

    /**
     * @return false|mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    public function getCurrentCardData(){
        $customerId = $this->_customerSession->getCustomerId();
        $openpayCustomerId = $this->_openpayAPI->hasOpenpayAccount($customerId);

        if(!$openpayCustomerId){
            return false;
        }

        $currentCard = $this->_openpayAPI->getCardList($openpayCustomerId->openpay_id);

        if(!$currentCard){
           return false;
        }

        return $currentCard[0]->serializableData;
    }

}
