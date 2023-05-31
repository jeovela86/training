<?php

namespace WolfSellers\PaymentLink\Plugin\Redirect;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Framework\UrlInterface;
use WolfSellers\PaymentLink\Model\Payment\PaymentLink;

class RedirectPlugin
{

    /** @var ResultFactory  */
    protected $_resultFactory;

    /** @var Session */
    protected $_checkoutSession;

    /** @var UrlInterface */
    protected $_url;

    public function __construct(
        ResultFactory $resultFactory,
        Session $checkoutSession,
        UrlInterface $url
    ) {
        $this->_resultFactory = $resultFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->_url = $url;
    }

    /**
     * @param Success $subject
     * @param $result
     * @return ResultInterface|mixed
     */
    public function afterExecute(Success $subject, $result) // @codingStandardsIgnoreLine
    {

        $order = $this->_checkoutSession->getLastRealOrder();

        if ($order &&
            $order->getStatus() == Order::STATE_PENDING_PAYMENT &&
            $order->getPayment()->getMethod() == PaymentLink::PAYMENT_METHOD_CODE
        ) {
            /** @var ResultInterface $result */
            $result = $this->_resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $result->setUrl($this->_url->getUrl('paymentlink/subscription/readytopay'));
            return $result;
        }

        return $result;
    }
}
