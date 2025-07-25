<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;

class DisableOrderEmailForPaypalExpress implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment && $payment->getMethod() === Button::PAYPAL_EXPRESS_METHOD_NAME) {
            $order->setCanSendNewEmailFlag(false);
        }
    }
}
