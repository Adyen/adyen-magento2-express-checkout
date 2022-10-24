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

namespace Adyen\ExpressCheckout\Model;

use Magento\Payment\Model\InfoInterface;

class IsExpressMethodResolver implements IsExpressMethodResolverInterface
{
    /**
     * @var array
     */
    private $expressMethodCodes;

    /**
     * @param array $expressMethodCodes
     */
    public function __construct(
        array $expressMethodCodes = []
    ) {
        $this->expressMethodCodes = $expressMethodCodes;
    }

    /**
     * Return boolean on whether payment method is an express method code
     *
     * @param InfoInterface $paymentInfo
     * @return bool
     */
    public function execute(
        InfoInterface $paymentInfo
    ): bool {
        $paymentAdditionalInfo = $paymentInfo->getAdditionalInformation() ?: [];
        $paymentBrandCode = $paymentAdditionalInfo['brand_code'] ?? null;
        return $paymentBrandCode !== null &&
            in_array(
                $paymentBrandCode,
                $this->expressMethodCodes
            );
    }
}
