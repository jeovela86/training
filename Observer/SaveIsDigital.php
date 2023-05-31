<?php
namespace WolfSellers\PaymentLink\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Webapi\Controller\Rest\InputParamsResolver;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Quote\Model\Quote\Payment;
use Psr\Log\LoggerInterface;
use WolfSellers\Attachments\Helper\Attach;
use Magento\Checkout\Model\Cart as CustomerCart;

class SaveIsDigital implements ObserverInterface
{
    /**
     * @var InputParamsResolver
     */
    protected $_inputParamsResolver;
    /**
     * @var QuoteRepository
     */
    protected $_quoteRepository;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var State
     */
    protected $_state;

    /**
     * @var CheckoutSession
     */
    protected $_checkSession;
    protected $_cart;

    public function __construct(
        InputParamsResolver $inputParamsResolver,
        QuoteRepository $quoteRepository,
        LoggerInterface $logger,
        State $state,
        CheckoutSession $checkSession,
        Attach $attachHelper,
        CustomerCart $cart
    ) {
        $this->_inputParamsResolver = $inputParamsResolver;
        $this->_quoteRepository = $quoteRepository;
        $this->_logger = $logger;
        $this->_state = $state;
        $this->_checkSession = $checkSession;
        $this->_attachHelper = $attachHelper;
        $this->_cart = $cart;
    }
    public function execute(EventObserver $observer)
    {
        $event = $observer->getEvent();
        $input = $event->getInput();
        /** @var \Magento\Quote\Model\Quote $quotes */
        $quotes = $event->getPayment()->getQuote();
        $additionalData = (array)$input->getAdditionalData();


        try {
            $quote = $this->_cart->getQuote();

            if(isset($additionalData['is_digital'])) {
                $isDigital = $additionalData['is_digital'];

                $subscriptionData = json_decode($quote->getData('subscription_data'), true);
                if (is_array($subscriptionData)) {
                    $result = json_encode(array_merge($subscriptionData, ['type_card'=>$isDigital] ));
                } else {
                    $result = json_encode(['type_card'=>$isDigital]);
                }
                $quote->setData('subscription_data', $result);

            }

        }catch(\Throwable $exception){
            $this->logger($exception);
        }

    }

    public function logger($message){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/subscriptions.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}
