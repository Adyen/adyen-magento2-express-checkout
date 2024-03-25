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
namespace Adyen\ExpressCheckout\Observer;

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Magento\Framework\Event\ObserverInterface;

class AddPaypalShortcuts extends AbstractPaymentMethodShortcuts implements ObserverInterface
{
    const SHORTCUT_BUTTON = Button::class;
    const PAYMENT_METHOD_VARIANT = Button::PAYPAL_VARIANT;
}
