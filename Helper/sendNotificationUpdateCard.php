<?php
/**
 * Created by.
 *
 * User: Juan Carlos Hdez <juanhernandez@wolfsellers.com>
 * Date: 2021-04-08
 * Time: 18:00
 */

declare(strict_types=1);

namespace WolfSellers\PaymentLink\Helper;

use WolfSellers\SubscriptionEmails\Helper\AbstractEmail;

/**
 * Benefits Email send.
 */
class sendNotificationUpdateCard extends AbstractEmail
{
    protected const XML_PATH = 'paymentlink_notifications';
    protected const XML_GROUP = 'paymentlink_recurrence_notification';
    public const XML_PATH_TEMPLATE = 'template';

    /**
     * @param int $customerId
     */
    public function send(int $customerId)
    {
        $customer = $this->getCustomerById($customerId);
        $this->sendEmailTemplate(
            $this->getPath(self::XML_PATH_TEMPLATE),
            $this->getPath(self::XML_PATH_SENDER),
            $this->getCustomerById($customerId),
            [ 'customer' => [ 'name' => $customer->getFirstname(). ' '. $customer->getLastname() ] ]
        );
    }
}
