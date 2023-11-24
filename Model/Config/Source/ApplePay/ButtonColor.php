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

namespace Adyen\ExpressCheckout\Model\Config\Source\ApplePay;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * @see https://docs.adyen.com/payment-methods/apple-pay/web-component/#ap-button-configuration
 */
class ButtonColor implements OptionSourceInterface
{
    public const BLACK = 'black';
    public const WHITE = 'white';
    public const WHITE_WITH_LINE = 'white-with-line';

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::BLACK,
                'label' => 'Black button',
            ],
            [
                'value' => self::WHITE,
                'label' => 'White button with no outline',
            ],
            [
                'value' => self::WHITE_WITH_LINE,
                'label' => 'White button with black outline',
            ],
        ];
    }
}
