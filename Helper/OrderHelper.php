<?php
namespace WolfSellers\PaymentLink\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Store\Model\ScopeInterface;
use WolfSellers\OpenpaySubscriptions\Helper\Order as OpenpayHelper;
use Magento\Customer\Model\Session as CustomerSession;
use WolfSellers\WebServicesLafam\Helper\OrderUtils;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;


class OrderHelper extends AbstractHelper
{
    CONST XML_PATH_PAYMENT_LINK_VALIDITY = 'paymentlink_notifications/paymentlink_configuration/payment_link_validity';

    /** @var OrderRepository  */
    protected $_orderRepository;

    /** @var TimezoneInterface */
    protected $_timezone;

    /** @var SearchCriteriaBuilder  */
    protected $_searchCriteriaBuilder;

    /** @var StoreManagerInterface */
    protected $_storeManager;

    /** @var FilterBuilder  */
    protected $_filterBuilder;

    /** @var SortOrderBuilder  */
    protected $_sortOrderBuilder;

    /** @var OpenpayHelper */
    protected $_openpayHelper;

    /** @var CustomerSession */
    protected $_customerSession;

    /** @var OrderUtils */
    protected $_orderUtils;

    /** @var InvoiceService */
    protected $_invoiceService;

    /** @var Transaction */
    protected $_transaction;

    public function __construct(
        Context $context,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        SortOrderBuilder $sortOrderBuilder,
        OpenpayHelper $openpayHelper,
        CustomerSession $customerSession,
        OrderUtils $orderUtils,
        TimezoneInterface $timezone,
        StoreManagerInterface $storeManager,
        InvoiceService $invoiceService,
        Transaction $transaction
    ) {
        parent::__construct($context);
        $this->_timezone = $timezone;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_sortOrderBuilder = $sortOrderBuilder;
        $this->_openpayHelper = $openpayHelper;
        $this->_customerSession = $customerSession;
        $this->_orderUtils = $orderUtils;
        $this->_storeManager = $storeManager;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
    }

    /**
     * @param int $customerId
     * @return false|mixed
     */
    public function getCustomerLastOrder(int $customerId)
    {
        $this->_searchCriteriaBuilder->addFilter(OrderInterface::CUSTOMER_ID, $customerId);
        $this->_searchCriteriaBuilder->addFilter(OrderInterface::STATUS, [Order::STATE_PENDING_PAYMENT], 'in');
        $searchCriteria = $this->_searchCriteriaBuilder->create();
        $searchCriteria->setSortOrders(
            [
                $this->_sortOrderBuilder
                    ->setField(OrderInterface::CREATED_AT)
                    ->setDirection(SortOrder::SORT_DESC)
                    ->create()
            ]
        );
        $searchResults = $this->_orderRepository->getList($searchCriteria);

        if($searchResults->getTotalCount() > 0){
            return current($searchResults->getItems());
        }

        return false;
    }

    /**
     * @param int $customerId
     * @return false|mixed
     */
    public function getLastOrderPaid(int $customerId)
    {
        $this->_searchCriteriaBuilder->addFilter(OrderInterface::CUSTOMER_ID, $customerId);
        $this->_searchCriteriaBuilder->addFilter(OrderInterface::STATUS, [Order::STATE_PENDING_PAYMENT], 'nin');
        $searchCriteria = $this->_searchCriteriaBuilder->create();
        $searchCriteria->setSortOrders(
            [
                $this->_sortOrderBuilder
                    ->setField(OrderInterface::CREATED_AT)
                    ->setDirection(SortOrder::SORT_DESC)
                    ->create()
            ]
        );
        $searchResults = $this->_orderRepository->getList($searchCriteria);

        if($searchResults->getTotalCount() > 0){
            return current($searchResults->getItems());
        }

        return false;
    }

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveOrder($order){
        return $this->_orderRepository->save($order);
    }

    /**
     * validate validity
     * @param $customerId
     * @return bool
     */
    public function validateValidity($customerId)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $this->getCustomerLastOrder($customerId);

        if(!$order){
            return false;
        }

        $currentDate = date('Y-m-d H:i:s'); //hora del servidor

        $hoursValidity = intval($this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_LINK_VALIDITY,
            ScopeInterface::SCOPE_STORE
        ));

        if($hoursValidity <= 0){
            return true;
        }

        $validityDate = date(
            "Y-m-d H:i:s",
            strtotime($order->getUpdatedAt() . " +" . $hoursValidity . " hour")
        );

        if($currentDate > $validityDate){ //comparacion entre horas del servidor
            return false;
        }

        return true;
    }

    /**
     * Regresa un valor booleano para indicar que es necesario notificar al cliente para que actualice su CVV
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateCVV(){
        if(!$this->_customerSession->isLoggedIn()) {
            return false;
        }

        $days = 1; // un dia antes se notificara que debe actualizar su cvv

        $currentTime = $this->_orderUtils->getDateByStoreId();

        $customerId = $this->_customerSession->getCustomerId();
        $subscriptions = $this->_openpayHelper->getAllActiveSubscriptionsByCustomerId($customerId);

        foreach($subscriptions as $subscription){
            if (
                $subscription &&
                $subscription->getStatus() == 1 &&
                $subscription->getIsDigital() &&
                !$subscription->getIsCardUpdated() &&
                $currentTime > date("Y-m-d H:i:s", strtotime($subscription->getPaymentDate() . " -" . $days . " days")) &&
                in_array($subscription->getPaymentMethod(),array('paymentlink','openpay_cards'))
            ){
                return true;
            }
        }

        return false;
    }


    /**
     * Regresa un valor booleano para indicar que es necesario notificar al cliente para que actualice su tarjeta
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateCard(){
        if(!$this->_customerSession->isLoggedIn()) {
            return false;
        }

        /*agregar la lógica para decidir si tiene que actualizar la tarjeta*/
        $days = 1; // un dia antes se notificara que debe actualizar su cvv

        $currentTime = $this->_orderUtils->getDateByStoreId();

        $customerId = $this->_customerSession->getCustomerId();
        $subscriptions = $this->_openpayHelper->getAllActiveSubscriptionsByCustomerId($customerId);

        foreach($subscriptions as $subscription) {
            if (
                $subscription &&
                $subscription->getStatus() == 1 &&
                !$subscription->getIsDigital() &&  // solo para tarjetas físicas
                !$subscription->getIsCardUpdated() &&
                $currentTime > date("Y-m-d H:i:s", strtotime($subscription->getPaymentDate())) &&
                in_array($subscription->getPaymentMethod(), array('paymentlink', 'openpay_cards'))
            ) {
                return true;
            }
        }

        return false;
    }

    public function createInvoice($orderId){

        $order = $this->_orderRepository->get($orderId);

        if ($order->canInvoice()) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();

            $transactionSave =
                $this->_transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
            $transactionSave->save();

            $order->addCommentToStatusHistory(
                __('Invoice was create Successfully #%1.', $invoice->getId())
            )->setIsCustomerNotified(false)->save();
        }
    }

    /**
     * @param int $customerId
     * @return false|mixed
     */
    public function getGeneralCustomerLastOrder(int $customerId)
    {
        $this->_searchCriteriaBuilder->addFilter(OrderInterface::CUSTOMER_ID, $customerId);
        //$this->_searchCriteriaBuilder->addFilter(OrderInterface::STATUS, [Order::STATE_PENDING_PAYMENT], 'in');
        $searchCriteria = $this->_searchCriteriaBuilder->create();
        $searchCriteria->setSortOrders(
            [
                $this->_sortOrderBuilder
                    ->setField(OrderInterface::CREATED_AT)
                    ->setDirection(SortOrder::SORT_DESC)
                    ->create()
            ]
        );
        $searchResults = $this->_orderRepository->getList($searchCriteria);

        if($searchResults->getTotalCount() > 0){
            return current($searchResults->getItems());
        }

        return false;
    }

}
