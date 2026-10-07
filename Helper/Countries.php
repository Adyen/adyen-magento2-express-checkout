<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Helper;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use Magento\Framework\Reflection\DataObjectProcessor;

readonly class Countries
{
    public function __construct(
        private CountryInformationAcquirerInterface $countryInformationAcquirer,
        private DataObjectProcessor $dataProcessor
    ) { }

    public function getCountries(): array
    {
        $countries = $this->countryInformationAcquirer->getCountriesInfo();

        $output = [];
        foreach ($countries as $country) {
            if (!empty($country->getFullNameLocale())) {
                $output[] = $this->dataProcessor->buildOutputDataArray($country,
                    CountryInformationInterface::class);
            }
        }

        return $output;
    }
}
