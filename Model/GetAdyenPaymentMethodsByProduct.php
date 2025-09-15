<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\Model\Checkout\PaymentMethodsRequest;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\AdyenAmountCurrencyFactory;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as TaxConfig;

class GetAdyenPaymentMethodsByProduct implements GetAdyenPaymentMethodsByProductInterface
{
    /**
     * @param AdyenAmountCurrencyFactory $adyenAmountCurrencyFactory
     * @param Data $adyenHelper
     * @param Config $adyenConfigHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenAmountCurrencyFactory $adyenAmountCurrencyFactory,
        private readonly Data $adyenHelper,
        private readonly Config $adyenConfigHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly AdyenLogger $adyenLogger
    ) {}

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

        /** @var AdyenAmountCurrency $adyenAmountCurrency */
        $adyenAmountCurrency = $this->adyenAmountCurrencyFactory->create([
            'amount' => $product->getFinalPrice(),
            'currencyCode' => $currencyCode
        ]);

        $paymentMethodsRequest = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store->getId()),
            "amount" => [
                "value" => $this->adyenHelper->formatAmount($adyenAmountCurrency->getAmount(), $currencyCode),
                "currency" => $currencyCode
            ]
        ];

        try {
            $adyenClient = $this->adyenHelper->initializeAdyenClient($store->getId());
            $service = $this->adyenHelper->initializePaymentsApi($adyenClient);

            $paymentMethodsRequestObject = new PaymentMethodsRequest($paymentMethodsRequest);

            $response = $service->paymentMethods($paymentMethodsRequestObject);
        } catch (Exception $exception) {
            $message = __('An error occurred while fetching Adyen payment methods on PDP. %1',
                $exception->getMessage());

            $this->adyenLogger->error($message);

            return [];
        }

        $responseData = [];
        $responseData['paymentMethodsResponse'] = $response->toArray();
        $responseData['paymentMethodsExtraDetails'] = [];

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
