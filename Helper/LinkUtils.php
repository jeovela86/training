<?php
namespace WolfSellers\PaymentLink\Helper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use WolfSellers\Subscriptions\Helper\Data;
use Magento\Framework\Api\SearchCriteriaBuilder;
use WolfSellers\Sellers\Api\SellerRepositoryInterface;
use WolfSellers\OpenpaySubscriptions\Helper\OpenpayAPI;
use Magento\Customer\Api\CustomerRepositoryInterface;

class LinkUtils extends AbstractHelper
{
    CONST PAYMENTLINK_CONTROLLER_URL = "paymentlink/subscription/token";

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var string
     */
    protected $temp_id;

    /** @var EncryptorInterface */
    protected $_encrypt;

    /** @var SearchCriteriaBuilder */
    protected $_searchCriteriaBuilder;

    /** @var SellerRepositoryInterface */
    protected $_sellerRepositoryInterface;

    /** @var OpenpayAPI */
    protected  $_openpayAPI;

    protected $_customerRepository;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation,
        EncryptorInterface $encrypt,
        Data $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SellerRepositoryInterface $sellerRepositoryInterface,
        OpenpayAPI $openpayAPI,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->_scopeConfig = $context;
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->_encrypt = $encrypt;
        $this->helperData = $helperData;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_sellerRepositoryInterface = $sellerRepositoryInterface;
        $this->_openpayAPI = $openpayAPI;
        $this->_customerRepository = $customerRepository;
    }

    /**
     * @param $path
     * @param $storeId
     * @return mixed
     */
    protected function getConfigValue($path, $storeId)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getController(){
        return $this->_storeManager->getStore()->getBaseUrl(). self::PAYMENTLINK_CONTROLLER_URL;
    }

    /**
     * @param $customerId
     * @return string
     */
    public function encryptCustomerId($customerId)
    {
        return $this->_encrypt->encrypt((string)$customerId);
    }

    /**
     * @param $data
     * @return string
     */
    public function decryptCustomerId($data){
        return $this->_encrypt->decrypt($data);
    }

    /**
     * @param $customerId
     * @return string
     */
    public function getPaymentLink($customerId){
        return $this->getController() ."?d=". urlencode($this->encryptCustomerId($customerId))."&process=charge";
    }

    public function getFormula($order): array
    {

        $leftEye = $this->helperData->getEye('Left',$order);
        $rightEye = $this->helperData->getEye('Right',$order);

        $ojos=[];
        $ojos['oi'] = '';
        $ojos['od'] = '';
        if($leftEye){
            $formula = 'OI';
            if ($leftEye->getAttributeText('curvabase')){
                $formula .= ', curva:'.$leftEye->getAttributeText('curvabase');
            }
            if ($leftEye->getAttributeText('esfera')){
                $formula .= ', esfera:'.$leftEye->getAttributeText('esfera');
            }
            if ($leftEye->getAttributeText('cilindro')){
                $formula .= ', cilindro:'.$leftEye->getAttributeText('cilindro');
            }
            if ($leftEye->getAttributeText('eje')){
                $formula .= ', eje:'.$leftEye->getAttributeText('eje');
            }
            if ($leftEye->getAttributeText('diametro')){
                $formula .= ', diametro:'.$leftEye->getAttributeText('diametro');
            }
            $ojos['oi'] = $formula;
        }

        if($rightEye){
            $formula = "OD";
            if ($rightEye->getAttributeText('curvabase')){
                $formula .= ', curva:'.$rightEye->getAttributeText('curvabase');
            }
            if ($rightEye->getAttributeText('esfera')){
                $formula .= ', esfera:'.$rightEye->getAttributeText('esfera');
            }
            if ($rightEye->getAttributeText('cilindro')){
                $formula .= ', cilindro:'.$rightEye->getAttributeText('cilindro');
            }
            if ($rightEye->getAttributeText('eje')){
                $formula .= ', eje:'.$rightEye->getAttributeText('eje');
            }
            if ($rightEye->getAttributeText('diametro')){
                $formula .= ', diametro:'.$rightEye->getAttributeText('diametro');
            }
            $ojos['od'] = $formula;
        }
        return $ojos;
    }

    public function getEmployeeById($employeeId)
    {
        /** @var \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteria */
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('paol_code',$employeeId,'eq' )
            ->create();

        $seller = $this->_sellerRepositoryInterface->getList($searchCriteria);

        if($seller->getTotalCount() < 0)
        {
            return false;
        }

        return current($seller->getItems());
    }

    public function customFormatAddres($address): string
    {
        $address = $address->getData();
        $pais = ($address['country_id'] == 'CO') ? 'Colombia' : $address['country_id'];
        return $address['street'] . ', ' . $address['city'] . ', ' . $address['region'] . ' ' . $address['postcode'] . ', ' . $pais;
    }

    public function getCustomerDataByItem($order, $item){
        $subscriptionData = json_decode($order->getSubscriptionData(), true);
        return $subscriptionData['customerData'][$item] ?? '';
    }

    public function getCustomerCards($customerId){
        try{
            $customerAccount = $this->_openpayAPI->hasOpenpayAccount($customerId);
            if ($customerAccount){
                $cards = $this->_openpayAPI->getCardList($customerAccount->openpay_id);

                if(!$cards){
                    return false;
                }
                return $cards[0];
            }else{
                return false;
            }
        } catch (\Throwable $error){
            return false;
        }
    }

    public function getCustomerDocumentValue($customerId){
        $customer = $this->_customerRepository->getById($customerId);
        return $customer->getCustomAttribute('document_numero')->getValue()?? '';
    }
}
