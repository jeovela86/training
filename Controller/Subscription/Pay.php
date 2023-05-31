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
use Magento\Sales\Model\Order\Address\Renderer;
use WolfSellers\OpenpaySubscriptions\Helper\UpdateSubscription;

class Pay extends Action implements CsrfAwareActionInterface
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

    /** @var SubscriptionsRepositoryInterface */
    protected $_subscription=null;

    /** @var Renderer */
    protected  $_addressRendered;

    /** @var UpdateSubscription */
    protected $_updateSubscription;

    protected $_cardCreated;

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
        Renderer $addresRenderer,
        UpdateSubscription $updateSubscription
    ) {

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
        $this->_addressRendered = $addresRenderer;
        $this->_updateSubscription = $updateSubscription;
        parent::__construct($context);

        $this->_cardCreated = '';
    }

    public function execute()
    {
        try{
            $openpayCustomerId="";
            $token = $this->_request->getParam('token_id');
            $customerId = urldecode($this->_request->getParam('customer_id'));
            $customerId = str_replace(" ","+",$customerId);
            $deviceSessionId = $this->_request->getParam('deviceIdHiddenFieldName');
            $action = $this->_request->getParam('process');
            $cardType = $this->_request->getParam('is_digital');

            $customerId = $this->_linkUtils->decryptCustomerId($customerId);

            $customer = $this->_customerRepository->getById($customerId);

            if(!$customer){
                throw new \Exception('El cliente no existe.');
            }

            /*se valida si la suscripcion/orden ya se pago*/
            $order = $this->_orderHelper->getCustomerLastOrder($customerId);

            if((!$order || !$this->_orderHelper->validateValidity($customerId)) && $action == 'charge'){
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl($this->_redirect->getRefererUrl());
                return $resultRedirect;
            }

            /* el cliente ya tiene una cuenta openpay registrada en magento?*/
            $openpayCustomerMagento = $this->_openpayAPI->hasOpenpayAccount($customerId);
            if($openpayCustomerMagento){
                $openpayCustomerId = $openpayCustomerMagento->openpay_id;
            }

            if(!$openpayCustomerId){
                /* Se crea el customer en openpay*/
                $openpayCustomerId = $this->_openpayCRUD->createCustomer($customer);
            }

            if($action == 'charge'){
                //Enable subscription by status
                $subscription = $this->_helperSubscription->getSubscriptionOffByCustomerId($customerId, 'DESC');
                if($subscription){
                    $subscription->setStatus(1);
                    $subscriptionCreated = $this->_subscriptionsRepository->save($subscription);
                }
                
                $this->_cardCreated = $this->_openpayCRUD->createCardWithToken($openpayCustomerId,$token,$deviceSessionId);
                $this->charge($customerId,$openpayCustomerId,$this->_cardCreated,$deviceSessionId);

                $this->_messageManager->addSuccessMessage(__('El pago fue procesado correctamente.'));

                // se dispara evento de confirmacion de cobro exitoso
                $this->_eventManager->dispatch('paymentlink_success_charge',['order'=>$order]);

                $this->redirectSuccessPage($customerId);

                $this->UpdateTypeCard($customerId,$cardType);

                return $this->_redirect('checkout/onepage/success');

            } elseif ($action == 'updateCard') {
                $this->_openpayCRUD->updateCard($openpayCustomerId,$token,$deviceSessionId);
                $this->_messageManager->addSuccessMessage(__('Su medio de Pago ha sido actualizada correctamente.'));
                $this->UpdateTypeCard($customerId,$cardType);

                $subscription = $this->_helperSubscription->getSubscriptionByCustomerId($customerId);

                if($subscription->getOpenpaySubscriptionCreated() == 2){
                    //se resetean las banderas para volver intentar generar la suscripcion en openpay
                    $subscription->setOpenpaySubscriptionCreated(0);
                    $subscription->setOpenpayStoreOrderPaid(0);
                    $this->_subscriptionsRepository->save($subscription);

                }elseif($subscription->getOpenpaySubscriptionCreated() == 1) {
                    // si ya existe una suscripcion en openpay se genera otra nueva
                    $this->_updateSubscription->updateOpenpaySubscription($subscription);
                }
            }

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;

        } catch (\Throwable $error){

            if($this->_cardCreated){ // si se habia creado una tarjeta se borra
                $this->_openpayCRUD->deleteCard($openpayCustomerId,$this->_cardCreated);
            }

            //Disable subscription if something wrong ocurred
            $subscription = $this->_helperSubscription->getSubscriptionByCustomerId($customerId);
            if($subscription){
                $subscription->setStatus(0);
                $subscriptionCreated = $this->_subscriptionsRepository->save($subscription);
            }

            $this->_messageManager->addErrorMessage(__($error->getMessage()));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());

            $this->_paymentLinkLogger->addError(
                "ERROR PAY CONTROLLER PRINCIPAL: ".
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

    public function charge($magentoCustomerId,$openpayCustomerId,$card,$deviceSessionId)
    {
        /** @var OrderInterface $order */
        $order = $this->_orderHelper->getCustomerLastOrder($magentoCustomerId);

        if(!$order){
            throw new \Exception('No existe una orden pendiente de pago.');
        }

        try{
            $charge = $this->_openpayCRUD->createCustomerCharge(
                $card,
                $order->getIncrementId(),
                $order->getGrandTotal(),
                $order->getOrderCurrencyCode(),
                $deviceSessionId,
                $openpayCustomerId,
                "Charge GrandVision Subscription"
            );

            $order->getPayment()->setAdditionalInformation(
                array_merge(
                    $order->getPayment()->getAdditionalInformation(),
                    [
                        'confirmation_number' => $charge->authorization,
                        'card_type' => $charge->card->type,
                        'operation_date' => $charge->operation_date,
                        'amount' => $charge->amount,
                    ]
                )
            );

            $order->getPayment()->setCcLast4(substr($charge->card->card_number, -4));

            $this->_orderHelper->saveOrder($order);

            $this->_paolHelper->sendToPaol($order);

        }catch (\Throwable $error){
            $this->_messageManager->addErrorMessage(__($error->getMessage()));
            $this->_paymentLinkLogger->addError(
                "ERROR PAY CONTROLLER, CHARGE: ".
                $error->getMessage().
                $error->getTraceAsString()
            );
            throw new \Exception('The customer charge was not created.');
        }
    }

    public function redirectSuccessPage($magentoCustomerId){
        $order = $this->_orderHelper->getLastOrderPaid($magentoCustomerId);

        if(!$this->_customerSession->isLoggedIn()){
            $customer = $this->_customerFactory->create()->load($magentoCustomerId);
            $sessionManager = $this->_sessionFactory->create();
            $sessionManager->setCustomerAsLoggedIn($customer);
        }
        $order = $this->_orderModel->load($order->getId());

        $this->_checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->_checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->_checkoutSession->setLastOrderId($order->getId());
        $this->_checkoutSession->setLastRealOrderId($order->getIncrementId());

        $order->setCanSendNewEmailFlag(true);
        $order->setSendEmail(true);
        $order->save();

        $this->_orderSender->send($order);

        /*notificacion a la tienda*/
        $this->storeNotification($magentoCustomerId);
    }

    public function storeNotification($magentoCustomerId){
        try{
            $asesorName = '';
            $order = $this->_orderHelper->getLastOrderPaid($magentoCustomerId);

            $order = $this->_orderModel->load($order->getId());

            $subscriptionData = json_decode($order->getData('subscription_data'),true);
            $storeId = $subscriptionData['customerData']['employee_store'];
            $employeeId = $subscriptionData['customerData']['employee_id'] ?? null;
            $documentIdentification = $subscriptionData['customerData']['documentTypeValue'] ?? null;
            $cellphone = $subscriptionData['customerData']['cellphone'] ?? null;
            $deliveredInStore = $subscriptionData['customerData']['delivered_in_store'] ?? false;

            /** @var \WolfSellers\Sellers\Api\Data\SellerInterface $employee */
            $employee = $this->_linkUtils->getEmployeeById($employeeId);
            if($employee){
                $asesorName = $employee->getFirstName();
            }

            $searchCriteria = $this->_searchCriteriaBuilder
                ->addFilter('number',$storeId,'eq' )
                ->create();

            $store = $this->_storeInterface->getList($searchCriteria);

            $customerId = $order->getCustomerId();
            $subscription = $this->_helperSubscription->getSubscriptionByCustomerId($customerId);

            if($store->getTotalCount() > 0) {
                /** @var \WolfSellers\Sellers\Api\Data\SellerStoreInterface $store */
                $store = current($store->getItems());

                $entrega = $deliveredInStore ? 'Entregado en tienda' : $this->_linkUtils->customFormatAddres($order->getShippingAddress());

                $templateVariables = [
                    'store' => [
                        'name' => $order->getStore()->getName(),
                    ],
                    'order_data' => [
                        'customer_name' => $order->getCustomerName(),
                        'is_not_virtual' => $order->getIsNotVirtual(),
                        'email_customer_note' => $order->getEmailCustomerNote(),
                        'frontend_status_label' => $order->getFrontendStatusLabel()
                    ],
                    'customer' => [
                        'id' => $documentIdentification,
                        'name' => $order->getCustomerName(),
                        'email' => $order->getCustomerEmail(),
                        'telephone' => $order->getShippingAddress()->getTelephone() ?? $cellphone
                    ],
                    'subscription' => [
                        'consecutive_number' => $subscription->getConsecutiveNumber(),
                        'product' => $subscription->getProductName(),
                        'formula' => $this->_linkUtils->getFormula($order),
                        'frequency' => $subscription->getPeriodicity(),
                        'date_delivery' => $entrega,
                        'asesor_name' => $asesorName
                    ]
                ];
                $transportObject = new DataObject($templateVariables);

                if($store->getEmail()){
                    $this->_transaction->sendStoreNotification(
                        $transportObject->getData(),
                        array('email'=>$store->getEmail(),'name'=>$store->getName())
                    );
                }
            }

        }catch (\Throwable $error){
            $this->_paymentLinkLogger->addError(
                "ERROR PAY CONTROLLER, STORE NOTIFICATION: ".
                $error->getMessage().
                $error->getTraceAsString()
            );
        }
    }

    /**
     * Actualiza el tipo de tarjeta dentro del campo de la suscripcion isDigital
     * @param $customerId
     * @param $cardType
     */
    public function UpdateTypeCard($customerId,$cardType){
        try {
            $subscription = $this->getSubscriptionByCustomerId($customerId);
            $subscription->setIsDigital($cardType);
            $subscription->setIsCardUpdated(!$cardType);
            $this->_subscriptionsRepository->save($subscription);

        }catch(\Throwable $exception){
            $this->_paymentLinkLogger->addError(
                "ERROR PAY CONTROLLER, ACTUALIZACION DE TIPO DE TARJETA: ".
                $exception->getMessage().
                $exception->getTraceAsString()
            );
        }
    }

    /**
     * @param $customerId
     * @return false|\WolfSellers\Subscriptions\Api\Data\SubscriptionsInterface|SubscriptionsRepositoryInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSubscriptionByCustomerId($customerId){
        if(!$this->_subscription){
            $this->_subscription = $this->_helperSubscription->getSubscriptionByCustomerId($customerId);
        }
        return $this->_subscription;
    }

}

