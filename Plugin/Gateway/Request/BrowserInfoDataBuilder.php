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

namespace Adyen\ExpressCheckout\Plugin\Gateway\Request;

use Adyen\Payment\Gateway\Request\BrowserInfoDataBuilder as Subject;
use Magento\Framework\Locale\Resolver as LocaleResolver;

class BrowserInfoDataBuilder
{
    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        LocaleResolver $localeResolver
    ) {
        $this->localeResolver = $localeResolver;
    }

    /**
     * After build intercept and ensure language is set in browser info.
     *
     * The Adyen Web Components SDK collects browserInfo.language client-side;
     * when it doesn't (e.g. after the v6 SDK upgrade, or unsupported browsers),
     * Adyen refuses 3DS2 authorisations with "Required field language missing
     * for device channel browser". Back-filling from the store locale here
     * applies to every payment method, not just express wallets.
     *
     * @param Subject $subject
     * @param array $result
     * @param array $buildSubject
     * @return array
     */
    public function afterBuild(
        Subject $subject,
        array $result,
        array $buildSubject
    ): array {
        $languageSet = $result['body']['browserInfo']['language'] ?? null;
        $currentLocale = $this->getCurrentStoreLanguageCode();
        if ($languageSet === null ||
            ($currentLocale !== null && $languageSet !== $currentLocale)) {
            $result['body']['browserInfo']['language'] = $currentLocale;
        }
        return $result;
    }

    /**
     * Return the current store locale as a BCP-47 language tag
     * (e.g. "en-GB"), matching the format a browser's navigator.language
     * would report.
     *
     * @return string|null
     */
    private function getCurrentStoreLanguageCode(): ?string
    {
        $currentLocale = $this->localeResolver->getLocale() ?: null;
        if ($currentLocale !== null) {
            $currentLocale = str_replace('_', '-', $currentLocale);
        }
        return $currentLocale;
    }
}
