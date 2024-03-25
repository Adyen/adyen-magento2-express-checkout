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

namespace Adyen\ExpressCheckout\Block\GooglePay\Shortcut;

use Adyen\ExpressCheckout\Block\Buttons\AbstractButton;
use Magento\Catalog\Block\ShortcutInterface;

class Button extends AbstractButton implements ShortcutInterface
{
    const GOOGLE_PAY_VARIANT = 'google_pay';
}
