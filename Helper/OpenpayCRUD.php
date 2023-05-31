<?php
namespace WolfSellers\PaymentLink\Helper;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Api\SortOrder;
use WolfSellers\OpenpaySubscriptions\Helper\OpenpayAPI;
use Magento\Customer\Api\Data\CustomerInterface;


class OpenpayCRUD extends AbstractHelper
{
    /** @var OrderRepository  */
    protected $_orderRepository;

    /** @var SearchCriteriaBuilder  */
    protected $_searchCriteriaBuilder;

    /** @var FilterBuilder  */
    protected $_filterBuilder;

    /** @var SortOrderBuilder  */
    protected $_sortOrderBuilder;

    /** @var OpenpayAPI */
    protected $_openpayAPI;

    /**
     * @var AddressRepositoryInterface
     */
    protected $_addressRepository;

    public function __construct(
        Context $context,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        SortOrderBuilder $sortOrderBuilder,
        OpenpayAPI $openpayAPI,
        AddressRepositoryInterface $addressRepository
    ) {
        parent::__construct($context);
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_sortOrderBuilder = $sortOrderBuilder;
        $this->_openpayAPI = $openpayAPI;
        $this->_addressRepository = $addressRepository;
    }

    /**
     * @param CustomerInterface $customer
     */
    public function createCustomer(CustomerInterface $customer){
        /* Creacion del cliente en openpay */
        $shippingAddressId = $customer->getDefaultShipping();
        $shippingAddress = $this->_addressRepository->getById($shippingAddressId);
        $telephone = $shippingAddress->getTelephone();

        /** @var \Magento\Customer\Api\Data\CustomerInterface $customerCreated */
        $openpayCustomer = $this->_openpayAPI->createCustomer(
            $customer->getFirstname(),
            $customer->getLastname(),
            $customer->getEmail(),
            $telephone
        );

        if(!$openpayCustomer){
            throw new \Exception('The openpay customer was not created.');
        }

        $this->_openpayAPI->saveOpenpayCustomerInMagento($customer->getId(),$openpayCustomer->id);

        return $openpayCustomer->id;
    }

    /**
     * @param $openpayCustomerId
     * @param $token
     * @param $deviceSessionId
     * @return bool
     * @throws \Exception
     */
    public function createCardWithToken($openpayCustomerId,$token,$deviceSessionId) {
        try{
            $cardCreated = $this->_openpayAPI->createCardWithToken(
                $openpayCustomerId,
                $token,
                $deviceSessionId
            );
            return $cardCreated->id;
        } catch (\Throwable $error){
            throw new \Exception($error->getMessage());
        }
    }

    /**
     * @param $cardId
     * @param $incrementalOrderId
     * @param $grandTotal
     * @param $currencyCode
     * @param $deviceSessionId
     * @param $openpayCustomerId
     * @param $description
     * @return bool
     * @throws \Exception
     */
    public function createCustomerCharge(
        $cardId,
        $incrementalOrderId,
        $grandTotal,
        $currencyCode,
        $deviceSessionId,
        $openpayCustomerId,
        $description)
    {
        $charge = $this->_openpayAPI->createCharge(
            $cardId,
            $incrementalOrderId,
            $grandTotal,
            $currencyCode,
            $deviceSessionId,
            $openpayCustomerId,
            $description
        );

        if(!$charge){
            throw new \Exception('The customer charge was not created.');
        }

        return $charge;
    }

    /**
     * @param $openpayCustomerId
     * @param $token
     * @param $deviceSessionId
     * @return bool
     * @throws \Exception
     */
    public function updateCard($openpayCustomerId,$token,$deviceSessionId){
        try{
            $currentCard = $this->_openpayAPI->getCardList($openpayCustomerId);

            $created = $this->createCardWithToken($openpayCustomerId,$token,$deviceSessionId);

            /*si se crea la nueva tarjeta se elimina la anterior*/
            $this->_openpayAPI->deleteCard($openpayCustomerId,$currentCard[0]->id);

            return true;
        } catch(\Throwable $error){
            throw new \Exception($error->getMessage());
        }
    }

    /**
     * @param $cardId
     */
    public function deleteCard($openpayCustomerId,$cardId){
        try{
            /*si se crea la nueva tarjeta se elimina la anterior*/
            $this->_openpayAPI->deleteCard($openpayCustomerId,$cardId);

            return true;
        } catch(\Throwable $error){
            throw new \Exception($error->getMessage());
        }
    }
}
