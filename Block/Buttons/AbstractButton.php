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

namespace Adyen\ExpressCheckout\Block\Buttons;

use Adyen\Payment\Helper\Config;
use Adyen\ExpressCheckout\Model\AgreementsProvider;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

abstract class AbstractButton extends Template
{
    public const ALIAS_ELEMENT_INDEX = 'alias';
    public const BUTTON_ELEMENT_INDEX = 'button_id';
    public const COUNTRY_CODE_PATH = 'general/country/default';

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var MethodInterface
     */
    protected $payment;

    /**
     * @var UrlInterface $url
     */
    protected $url;

    /**
     * @var CustomerSession $customerSession
     */
    protected $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    protected $scopeConfig;

    /**
     * @var AdyenHelper
     */
    protected $adyenHelper;

    /**
     * @var Config
     */
    protected $adyenConfigHelper;

    /**
     * @var AgreementsProvider
     */
    protected $agreementsProvider;

    /**
     * AbstractButton Constructor
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Model\MethodInterface $payment
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Helper\Config $adyenConfigHelper
     * @param \Adyen\ExpressCheckout\Model\AgreementsProvider $agreementsProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        UrlInterface $url,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfig,
        AdyenHelper $adyenHelper,
        Config $adyenConfigHelper,
        AgreementsProvider $agreementsProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->payment = $payment;
        $this->url = $url;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManagerInterface;
        $this->scopeConfig = $scopeConfig;
        $this->adyenHelper = $adyenHelper;
        $this->adyenConfigHelper = $adyenConfigHelper;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string // @codingStandardsIgnoreLine
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->payment->isAvailable($this->checkoutSession->getQuote());
    }

    /**
     * Cart grand total
     *
     * @return float|null
     */
    public function getAmount()
    {
        return $this->checkoutSession->getQuote()->getBaseGrandTotal();
    }

    /**
     * URL To success page
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * Is customer logged in flag
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStorecode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * @return string
     */
    public function getDefaultCountryCode(): ?string
    {
        return $this->scopeConfig->getValue(
            self::COUNTRY_CODE_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrency()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        return $this->checkoutSession->getQuote()
            ->getCurrency()
            ->getBaseCurrencyCode() ?: $store->getBaseCurrencyCode();
    }

    /**
     * @return string
     */
    public function getMerchantAccount(): ?string
    {
        return $this->adyenHelper->getAdyenMerchantAccount(
            '',
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->adyenHelper->getStoreLocale(
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * @return string
     */
    public function getFormat(): int
    {
        return $this->adyenHelper->decimalNumbers($this->getCurrency());
    }

    /**
     * @return string
     */
    public function getOriginKey(): ?string
    {
        return $this->adyenHelper->getOriginKeyForBaseUrl();
    }

    /**
     * @return string
     */
    public function getCheckoutEnvironment(): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return $this->adyenHelper->getCheckoutEnvironment($storeId);
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX) ?: '';
    }

    /**
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX) ?: '';
    }

    /**
     * @return array
     */
    public function getAgreementIds(): array
    {
        return $this->agreementsProvider->getRequiredAgreementIds();
    }
}
