<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

class SubmitQuoteObserver implements ObserverInterface
{
    /**
     * Execute method for the observer.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        if ($payment->getMethod() == 'adyen_paypal_express') {
            $payment->setMethod('adyen_paypal');
        }

        $order->setState(Order::STATE_NEW);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order:: STATE_NEW));
        $order->save();
    }
}
