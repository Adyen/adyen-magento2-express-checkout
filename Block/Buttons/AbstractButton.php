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

use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\Config;
use Exception;
use Magento\Checkout\Model\DefaultConfigProvider;
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
    private Session $checkoutSession;

    /**
     * @var MethodInterface
     */
    private MethodInterface $payment;

    /**
     * @var UrlInterface $url
     */
    private UrlInterface $url;

    /**
     * @var CustomerSession $customerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var AdyenHelper
     */
    private AdyenHelper $adyenHelper;

    /**
     * @var Locale
     */
    private Locale $localeHelper;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var DefaultConfigProvider
     */
    private DefaultConfigProvider $defaultConfigProvider;

    /**
     * Button constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     * @param ScopeConfigInterface $scopeConfig
     * @param AdyenHelper $adyenHelper
     * @param Locale $localeHelper
     * @param Config $configHelper
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param array $data
     * @paramm Config $configHelper
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
        Locale $localeHelper,
        Config $configHelper,
        DefaultConfigProvider $defaultConfigProvider,
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
        $this->localeHelper = $localeHelper;
        $this->configHelper = $configHelper;
        $this->defaultConfigProvider = $defaultConfigProvider;
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
     * @return string|null
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
    public function getCurrency(): ?string
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        return $this->checkoutSession->getQuote()
            ->getCurrency()
            ->getBaseCurrencyCode() ?: $store->getBaseCurrencyCode();
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getMerchantAccount(): ?string
    {
        return $this->adyenHelper->getAdyenMerchantAccount(
            '',
            $this->storeManager->getStore()->getId()
        );
    }

    public function getLocale(): string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        return $this->localeHelper->getStoreLocale(
            $storeId
        );
    }

    /**
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getFormat(): int
    {
        return $this->adyenHelper->decimalNumbers($this->getCurrency());
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getOriginKey(): ?string
    {
        $environment = $this->configHelper->isDemoMode() ? 'test' : 'live';
        $storeId =(int) $this->storeManager->getStore()->getId();
        return $this->configHelper->getClientKey($environment, $storeId);
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCheckoutEnvironment(): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        return $this->adyenHelper->getCheckoutEnvironment($storeId);
    }

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

    public function getRandomElementId(): string
    {
        try {
            $id = sprintf('%s%s', $this->getContainerId(), random_int(PHP_INT_MIN, PHP_INT_MAX));
        } catch (Exception $e) {
            /**
             * Exception only thrown if an appropriate source of randomness cannot be found.
             * https://www.php.net/manual/en/function.random-int.php
             */
            $id = "0";
        }

        return $id;
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }
        return '';
    }

    /**
     * Returns Adyen payment method variant
     *
     * @return string
     */
    public function getPaymentMethodVariant(): string
    {
        return static::PAYMENT_METHOD_VARIANT;
    }

    /**
     * Returns the base configuration for express frontend
     *
     * @return array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildConfiguration(): array
    {
        $variant = $this->getPaymentMethodVariant();

        return [
            "Adyen_ExpressCheckout/js/$variant/button" => [
                'actionSuccess' => $this->getActionSuccess(),
                'storeCode' => $this->getStorecode(),
                'countryCode' => $this->getDefaultCountryCode(),
                'currency' => $this->getCurrency(),
                'merchantAccount' => $this->getMerchantAccount(),
                'format' => $this->getFormat(),
                'locale' => $this->getLocale(),
                'originkey' => $this->getOriginKey(),
                'checkoutenv' => $this->getCheckoutEnvironment(),
                'isProductView' => (bool) $this->getIsProductView()
            ]
        ];
    }
}
