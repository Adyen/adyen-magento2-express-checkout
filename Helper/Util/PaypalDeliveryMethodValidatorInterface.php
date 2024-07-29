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

namespace Adyen\ExpressCheckout\Helper\Util;

interface PaypalDeliveryMethodValidatorInterface
{
    const DELIVERY_METHOD_FIELD_REFERENCE = 'reference';
    const DELIVERY_METHOD_FIELD_DESCRIPTION = 'description';
    const DELIVERY_METHOD_FIELD_TYPE = 'type';
    const DELIVERY_METHOD_FIELD_AMOUNT = 'amount';
    const DELIVERY_METHOD_FIELD_SELECTED = 'selected';

    const DELIVERY_METHOD_FIELDS = [
        self::DELIVERY_METHOD_FIELD_REFERENCE,
        self::DELIVERY_METHOD_FIELD_DESCRIPTION,
        self::DELIVERY_METHOD_FIELD_TYPE,
        self::DELIVERY_METHOD_FIELD_AMOUNT,
        self::DELIVERY_METHOD_FIELD_SELECTED
    ];

    /**
     * Validates and clean-up the invalid data from PayPal's delivery methods and returns a valid array.
     *
     * @param array $deliveryMethod
     * @return array
     */
    public function getValidatedDeliveryMethod(array $deliveryMethod): array;
}
