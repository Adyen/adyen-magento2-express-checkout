<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\AdyenAmountCurrencyFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as TaxConfig;

class GetAdyenPaymentMethodsByProduct implements GetAdyenPaymentMethodsByProductInterface
{
    /**
     * @var AdyenAmountCurrencyFactory
     */
    private $adyenAmountCurrencyFactory;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Config
     */
    private $adyenConfigHelper;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param AdyenAmountCurrencyFactory $adyenAmountCurrencyFactory
     * @param Data $adyenHelper
     * @param Config $adyenConfigHelper
     * @param ChargedCurrency $chargedCurrency
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        AdyenAmountCurrencyFactory $adyenAmountCurrencyFactory,
        Data $adyenHelper,
        Config $adyenConfigHelper,
        ChargedCurrency $chargedCurrency,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->adyenAmountCurrencyFactory = $adyenAmountCurrencyFactory;
        $this->adyenHelper = $adyenHelper;
        $this->adyenConfigHelper = $adyenConfigHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return Adyen Retrieve Payment Methods response for Product without Quote
     * Used in PDP for ExpressCheckout when we don't have all options selected yet for Composite
     *
     * @param ProductInterface $product
     * @return array
     */
    public function execute(
        ProductInterface $product,
        CartInterface $quote
    ): array {
        $store = $quote->getStore();
        if (!$store) {
            return [];
        }
        $merchantAccount = $this->adyenConfigHelper->getAdyenAbstractConfigData(
            'merchant_account',
            (int)$store->getId()
        );
        if (!$merchantAccount) {
            return [];
        }
        $configuredChargeCurrency = $this->adyenConfigHelper->getChargedCurrency(
            $quote->getStoreId()
        );
        $currencyCode = $configuredChargeCurrency === ChargedCurrency::BASE ?
            $quote->getBaseCurrencyCode() :
            $quote->getCurrency()->getQuoteCurrencyCode();

        $adyenAmountCurrency = $this->adyenAmountCurrencyFactory->create([
            'amount' => $product->getFinalPrice(),
            'currencyCode' => $currencyCode
        ]);
        $paymentMethodRequest = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store->getId()),
            "amount" => [
                "value" => $this->adyenHelper->formatAmount($adyenAmountCurrency->getAmount(), $currencyCode),
                "currency" => $currencyCode
            ]
        ];
        $adyenClient = $this->adyenHelper->initializeAdyenClient(
            $store->getId()
        );
        $service = $this->adyenHelper->createAdyenCheckoutService($adyenClient);
        $response = $service->paymentMethods(
            $paymentMethodRequest,
            $store
        );
        if (!$response) {
            return [];
        }
        $responseData = [];
        $responseData['paymentMethodsResponse'] = $response;
        $responseData['paymentMethodsExtraDetails'] = [];
        if (!$response) {
            return [];
        }
        return $responseData;
    }

    /**
     * Return Current Country Code
     *
     * @param StoreInterface $store
     * @return string
     */
    private function getCurrentCountryCode(
        StoreInterface $store
    ): string {
        $countryCode = $this->adyenConfigHelper->getAdyenHppConfigData(
            'country',
            (int)$store->getId()
        );
        if ($countryCode) {
            return $countryCode;
        }
        return $this->scopeConfig->getValue(
            TaxConfig::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORES,
            $store->getCode()
        ) ?: "";
    }
}
