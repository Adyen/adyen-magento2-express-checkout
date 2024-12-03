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
namespace Adyen\ExpressCheckout\Observer;

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Magento\Framework\Event\ObserverInterface;

class AddPaypalShortcuts extends AbstractPaymentMethodShortcuts implements ObserverInterface
{
    public function __construct(
        ConfigurationInterface $configuration,
        Button $paypalButton
    ) {
        parent::__construct($configuration, $paypalButton);
    }
}
