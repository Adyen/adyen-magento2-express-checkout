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

use Adyen\Payment\Helper\Util\DataArrayValidator;

class PaypalDeliveryMethodValidator implements PaypalDeliveryMethodValidatorInterface
{
    public function getValidatedDeliveryMethod(array $deliveryMethod): array
    {
        if (!empty($deliveryMethod)) {
            $deliveryMethod = DataArrayValidator::getArrayOnlyWithApprovedKeys(
                $deliveryMethod,
                self::DELIVERY_METHOD_FIELDS
            );
        }

        return $deliveryMethod;
    }
}
