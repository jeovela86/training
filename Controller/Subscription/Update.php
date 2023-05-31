<?php

namespace WolfSellers\PaymentLink\Controller\Subscription;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DataObject;
use Magento\Framework\Phrase;
use WolfSellers\PaymentLink\Helper\LinkUtils;
use WolfSellers\OpenpaySubscriptions\Helper\OpenpayAPI;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use WolfSellers\PaymentLink\Helper\OrderHelper;
use Magento\Sales\Api\Data\OrderInterface;
use WolfSellers\PaymentLink\Helper\OpenpayCRUD;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use WolfSellers\PaymentLink\Helper\PaolHelper;
use WolfSellers\PaymentLink\Logger\PaymentLinkLogger;
use WolfSellers\Sellers\Api\SellerStoreRepositoryInterface as StoreInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use WolfSellers\PaymentLink\Helper\Transactional;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use WolfSellers\OpenpaySubscriptions\Helper\Order as OpenpayOrder;
use WolfSellers\Subscriptions\Api\SubscriptionsRepositoryInterface;
use WolfSellers\OpenpaySubscriptions\Helper\UpdateSubscription;

class Update extends Action implements CsrfAwareActionInterface
{

    /**
     * @var LinkUtils
     */
    protected $_linkUtils;

    /** @var OpenpayAPI */
    protected $_openpayAPI;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    protected $_addressRepository;

    /** @var OrderHelper */
    protected $_orderHelper;

    /** @var OpenpayCRUD */
    protected $_openpayCRUD;

    /** @var ManagerInterface */
    protected $_messageManager;

    /** @var CheckoutSession */
    protected $_checkoutSession;

    /** @var Order */
    protected $_orderModel;

    /** @var OrderSender */
    protected $_orderSender;

    protected $_storeInterface;

    protected $_searchCriteriaBuilder;

    /** @var Transactional */
    protected $_transaction;

    /** @var PaolHelper */
    protected $_paolHelper;

    /** @var CustomerFactory  */
    protected $_customerFactory;

    /** @var SessionFactory  */
    protected $_sessionFactory;

    /** @var CustomerSession */
    protected $_customerSession;

    /** @var PaymentLinkLogger */
    protected $_paymentLinkLogger;

    /** @var OpenpayOrder */
    protected $_helperSubscription;

    /** @var SubscriptionsRepositoryInterface */
    protected  $_subscriptionsRepository;

    /** @var CreateShipment */
    protected $_createShipment;

    /** @var UpdateSubscription */
    protected $_updateSubscription;

    public function __construct(
        Context $context,
        LinkUtils $linkUtils,
        OpenpayAPI $openpayAPI,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        OrderHelper $orderHelper,
        OpenpayCRUD $openpayCRUD,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        Order $orderModel,
        OrderSender $orderSender,
        StoreInterface $storeInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Transactional $transactional,
        PaolHelper $paolHelper,
        CustomerFactory $customerFactory,
        SessionFactory $sessionFactory,
        CustomerSession $customerSession,
        PaymentLinkLogger $paymentLinkLogger,
        OpenpayOrder $helperSubscription,
        SubscriptionsRepositoryInterface $subscriptionFactory,
        UpdateSubscription $updateSubscription
    ) {
        $this->_updateSubscription = $updateSubscription;
        $this->_customerFactory = $customerFactory;
        $this->_sessionFactory = $sessionFactory;
        $this->_customerSession = $customerSession;
        $this->_paolHelper = $paolHelper;
        $this->_linkUtils = $linkUtils;
        $this->_openpayAPI = $openpayAPI;
        $this->_customerRepository = $customerRepository;
        $this->_addressRepository = $addressRepository;
        $this->_orderHelper = $orderHelper;
        $this->_openpayCRUD = $openpayCRUD;
        $this->_messageManager = $messageManager;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderModel = $orderModel;
        $this->_orderSender = $orderSender;
        $this->_storeInterface = $storeInterface;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_transaction = $transactional;
        $this->_paymentLinkLogger = $paymentLinkLogger;
        $this->_helperSubscription = $helperSubscription;
        $this->_subscriptionsRepository = $subscriptionFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        try{
            $customerId = urldecode($this->_request->getParam('customer_id'));
            $customerId = str_replace(" ","+",$customerId);
            $cvv = $this->_request->getParam('CxsEe');

            $customerId = $this->_linkUtils->decryptCustomerId($customerId);

            if(!preg_match("/^[0-9]{3,4}$/",$cvv)){ //valida la estructura del cvv ingresado
                $this->_messageManager->addErrorMessage(__("El CVV proporcionado no es válido."));
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl($this->_redirect->getRefererUrl());

                $this->_paymentLinkLogger->addError(
                    "el CVV no es valido: ". $cvv. "customerId: ". $customerId
                );

                return $resultRedirect;
            }

            $customer = $this->_customerRepository->getById($customerId);

            if(!$customer){
                throw new \Exception('El cliente no existe.');
            }

            /* el cliente ya tiene una cuenta openpay registrada en magento?*/
            $openpayCustomerMagento = $this->_openpayAPI->hasOpenpayAccount($customerId);

            /* obtener el id de la tarjeta registrada actual*/
            $cardId = $this->getCurrentCardId($openpayCustomerMagento->openpay_id);

            $card = $this->_openpayAPI->updateCard(
                $openpayCustomerMagento->openpay_id,
                $cardId,
                array(
                    'cvv2'=>$cvv
                )
            );

            if(!$card){
                $this->_messageManager->addErrorMessage(__("No fue posible actualizar su CVV, intente mas tarde."));
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            }

            $subscriptions = $this->_helperSubscription->getAllActiveSubscriptionsByCustomerId($customerId);

            foreach($subscriptions as $subscription){
                $subscription->setIsCardUpdated(1);

                if($subscription->getOpenpaySubscriptionCreated() == 2){
                    //se resetean las banderas para volver intentar generar la suscripcion en openpay
                    $subscription->setOpenpaySubscriptionCreated(0);
                    $subscription->setOpenpayStoreOrderPaid(0);
                }elseif($subscription->getOpenpaySubscriptionCreated() == 1){
                    // si ya existe una suscripcion en openpay se genera otra nueva
                    $this->_updateSubscription->updateOpenpaySubscription($subscription);
                }

                $this->_subscriptionsRepository->save($subscription);
            }

            $this->_messageManager->addSuccessMessage(__("El CVV ha sido actualizado de forma correcta."));

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;

        } catch (\Throwable $error){
            $this->_messageManager->addErrorMessage(__($error->getMessage()));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());

            $this->_paymentLinkLogger->addError(
                "ERROR UPDATE CONTROLLER PRINCIPAL: ".
                $error->getMessage().
                $error->getTraceAsString()
            );

            return $resultRedirect;
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/');

        return new InvalidRequestException(
            $resultRedirect,
            [new Phrase('Invalid Form Key. Please refresh the page.')]
        );
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return false|mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    public function getCurrentCardId($openpayCustomerId){

        $currentCard = $this->_openpayAPI->getCardList($openpayCustomerId);

        if(!$currentCard){
            return false;
        }

        return $currentCard[0]->id;
    }
}

