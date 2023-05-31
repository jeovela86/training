<?php

namespace WolfSellers\PaymentLink\Controller\Customer;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\Http;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class ConfirmationEmail extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /** @var Http */
    protected $_request;

    /** @var JsonFactory */
    protected $_jsonFactory;

    /** @var CheckoutSession */
    protected $_checkoutSession;

    /** @var CartRepositoryInterface */
    protected $_cartRepository;

    /** @var CustomerRepositoryInterface */
    protected $_customerRepository;

    public function __construct(
        Context $context,
        Http $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->_request = $request;
        $this->_jsonFactory = $jsonFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_cartRepository = $cartRepository;
        $this->_customerRepository = $customerRepository;
    }

    public function execute() {

        $result = $this->_jsonFactory->create();
        $confirmationEmail = $this->_request->getParam('confirmation_email');
        $emailInQuote = $this->_request->getParam('email_in_quote');

        if($emailInQuote == $confirmationEmail){
            $result->setData(array('success'=>true,'message'=>''));
            return $result;
        }

        try {
            /*carga el customer ya creado con el anterior correo */
            $customer = $this->_customerRepository->get($emailInQuote);
            /*corrige el correo*/
            $customer->setEmail($confirmationEmail);
            $this->_customerRepository->save($customer);

            $result->setData(array('success'=>true,'message'=>''));
            return $result;

        } catch(\Throwable $error){
            $result->setData(
                array(
                    'success'=>false,
                    'message'=>'El correo electrónico ya se encuentra registrado a un cliente con una suscripción activa, intente con otro correo.'
                )
            );
            return $result;
        }
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
