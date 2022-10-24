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
use Adyen\ExpressCheckout\Model\IsExpressMethodResolverInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class BrowserInfoDataBuilder
{
    /**
     * @var IsExpressMethodResolverInterface
     */
    private $isExpressMethodResolver;

    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @param IsExpressMethodResolverInterface $isExpressMethodResolver
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        IsExpressMethodResolverInterface $isExpressMethodResolver,
        LocaleResolver $localeResolver
    ) {
        $this->isExpressMethodResolver = $isExpressMethodResolver;
        $this->localeResolver = $localeResolver;
    }

    /**
     * After build intercept and ensure language is set in browser info if express
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
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        if ($this->isExpressMethodResolver->execute($payment)) {
            $languageSet = $result['body']['browserInfo']['language'] ?? null;
            $currentLocale = $this->getCurrentStoreLanguageCode($buildSubject);
            if ($languageSet === null ||
                ($currentLocale !== null && $languageSet !== $currentLocale)) {
                $result['body']['browserInfo']['language'] = $currentLocale;
            }
        }
        return $result;
    }

    /**
     * Return Current Stores language
     *
     * @return string|null
     */
    private function getCurrentStoreLanguageCode(): ?string
    {
        $currentLocale = $this->localeResolver->getLocale() ?: null;
        if ($currentLocale !== null) {
            $languageCodeSplit = explode('_', $currentLocale);
            $currentLocale = $languageCodeSplit[1] ?? $currentLocale;
        }
        return $currentLocale;
    }
}
