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

use Magento\Framework\Exception\ValidatorException;

class PaypalDeliveryMethodValidator implements PaypalDeliveryMethodValidatorInterface
{
    /**
     * Validates the delivery method array
     *
     * @param array $deliveryMethod
     * @return array
     * @throws ValidatorException
     */
    public function getValidatedDeliveryMethod(array $deliveryMethod): array
    {
        if (empty($deliveryMethod)) {
            throw new ValidatorException(
                __('Shipping methods are missing.')
            );
        }

        $validatedDeliveryMethod = [];

        foreach (self::DELIVERY_METHOD_FIELDS as $key) {
            if (!array_key_exists($key, $deliveryMethod)) {
                throw new ValidatorException(
                    __("Missing required delivery method field: '%1'", [$key])
                );
            }

            $value = $deliveryMethod[$key];
            if ($value === null || $value === '') {
                throw new ValidatorException(
                    __("Delivery method field '%1' cannot be empty or null.", [$key])
                );
            }

            $validatedDeliveryMethod[$key] = $value;
        }

        return $validatedDeliveryMethod;
    }
}
