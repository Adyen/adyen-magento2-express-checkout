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

namespace Adyen\ExpressCheckout\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;

class AdyenExpress implements SectionSourceInterface
{
    /**
     * Return empty sectiond data array, used purely for FE and this added as an extension point for thirdparties
     *
     * @return array
     */
    public function getSectionData()
    {
        return [];
    }
}
