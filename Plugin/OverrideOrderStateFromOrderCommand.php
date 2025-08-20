<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment\State\OrderCommand;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;

class OverrideOrderStateFromOrderCommand
{
    public function aroundExecute(
        OrderCommand $subject,
        \Closure $proceed,
        OrderPaymentInterface $payment,
        $amount,
        OrderInterface $order
    ) {
        /* Only override for Adyen PayPal Express, as the payment_action is set to order
           by default, it sets the state and status to Processing. With the logic below, we
           intercept the OrderCommand to set state as NEW and status as Pending
        */
        if ($payment->getMethod() === Button::PAYPAL_EXPRESS_METHOD_NAME) {
            if ($payment->getIsTransactionPending() || $payment->getIsFraudDetected()) {
                return $proceed($payment, $amount, $order);
            }

            $order->setState(Order::STATE_NEW);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_NEW));

            // Skip Magento's default assignment of processing
            return __('Ordered amount of %1', $order->getBaseCurrency()->formatTxt($amount));
        }

        return $proceed($payment, $amount, $order);
    }
}
