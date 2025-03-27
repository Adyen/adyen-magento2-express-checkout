<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Observer;

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

/**
 * @deprecated
 */
class UpdatePaypalOrderStatus implements ObserverInterface
{
    /**
     * This observer is responsible for setting the payment method as `adyen_paypal` for express payments.
     * In addition, order status is set to `pending` with state `new` since order state is processing
     * by default as `/orders` endpoint is used to create the order while making express PayPal payments.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        if ($payment->getMethod() === Button::PAYPAL_EXPRESS_METHOD_NAME) {
            $payment->setMethod(Button::PAYPAL_METHOD_NAME);

            $order->setState(Order::STATE_NEW);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_NEW));
            $order->save();
        }
    }
}
