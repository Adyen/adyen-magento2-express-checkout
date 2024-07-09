<?php

namespace Vendor\ExpressCheckout\Observer;

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
        $order->setStatus(Order::STATE_NEW);
        $order->save();
    }
}
