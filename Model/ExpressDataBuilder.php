<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface as MethodResponseConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterface;
use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

class ExpressDataBuilder implements ExpressDataBuilderInterface
{
    private AdyenPaymentMethodsInterface $adyenPaymentMethods;
    private AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement;
    private ExtraDetailInterface $adyenPaymentMethodExtraDetail;
    private AmountInterface $adyenPaymentMethodConfigurationAmount;
    private ConfigurationInterface $adyenPaymentMethodExtraDetailConfiguration;
    private IconInterface $adyenPaymentMethodExtraDetailIcon;
    private MethodResponseInterface $adyenPaymentMethodResponse;
    private MethodResponseConfigurationInterface $adyenPaymentMethodResponseConfiguration;
    private CartTotalRepositoryInterface $cartTotalRepository;
    private ExpressDataInterface $expressData;
    private GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct;
    private QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     * @param AdyenPaymentMethodsInterface $adyenPaymentMethods
     * @param AmountInterface $adyenPaymentMethodConfigurationAmount
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param ConfigurationInterface $adyenPaymentMethodExtraDetailConfiguration
     * @param ExpressDataInterface $expressData
     * @param ExtraDetailInterface $adyenPaymentMethodExtraDetail
     * @param GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct
     * @param IconInterface $adyenPaymentMethodExtraDetailIcon
     * @param MethodResponseInterface $adyenPaymentMethodResponse
     * @param MethodResponseConfigurationInterface $adyenPaymentMethodResponseConfiguration
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement,
        AdyenPaymentMethodsInterface $adyenPaymentMethods,
        AmountInterface $adyenPaymentMethodConfigurationAmount,
        CartTotalRepositoryInterface $cartTotalRepository,
        ConfigurationInterface $adyenPaymentMethodExtraDetailConfiguration,
        ExpressDataInterface $expressData,
        ExtraDetailInterface $adyenPaymentMethodExtraDetail,
        GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct,
        IconInterface $adyenPaymentMethodExtraDetailIcon,
        MethodResponseInterface $adyenPaymentMethodResponse,
        MethodResponseConfigurationInterface $adyenPaymentMethodResponseConfiguration,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
        $this->adyenPaymentMethods = $adyenPaymentMethods;
        $this->adyenPaymentMethodConfigurationAmount = $adyenPaymentMethodConfigurationAmount;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->adyenPaymentMethodExtraDetailConfiguration = $adyenPaymentMethodExtraDetailConfiguration;
        $this->expressData = $expressData;
        $this->adyenPaymentMethodExtraDetail = $adyenPaymentMethodExtraDetail;
        $this->getAdyenPaymentMethodsByProduct = $getAdyenPaymentMethodsByProduct;
        $this->adyenPaymentMethodExtraDetailIcon = $adyenPaymentMethodExtraDetailIcon;
        $this->adyenPaymentMethodResponse = $adyenPaymentMethodResponse;
        $this->adyenPaymentMethodResponseConfiguration = $adyenPaymentMethodResponseConfiguration;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Build Express Data Object and set Data based on Quote
     *
     * @param CartInterface $quote
     * @param ProductInterface $product
     * @return ExpressDataInterface
     * @throws NoSuchEntityException
     */
    public function execute(
        CartInterface $quote,
        ProductInterface $product
    ): ExpressDataInterface {
        /** @var ExpressDataInterface $expressData */
        $expressData = $this->expressData->create();
        $adyenPaymentMethods = $this->getAdyenPaymentMethods(
            $quote,
            $product
        );
        $expressData->setAdyenPaymentMethods($adyenPaymentMethods);
        $maskedQuoteId = $this->getMaskedQuoteId($quote);
        $expressData->setMaskedQuoteId($maskedQuoteId);
        $expressData->setIsVirtualQuote($quote->isVirtual());
        $cartTotals = $this->cartTotalRepository->get(
            $quote->getId()
        );
        /** @var CartInterface|Quote $quote */
        $expressData->setTotals($cartTotals);
        return $expressData;
    }

    /**
     * Get Masked ID for given Quote
     *
     * @param CartInterface $quote
     * @return string
     * @throws NoSuchEntityException
     */
    private function getMaskedQuoteId(
        CartInterface $quote
    ): string {
        $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute(
            (int) $quote->getId()
        );
        if (!$maskedQuoteId) {
            /** @var QuoteIdMask $maskedQuoteId */
            $maskedQuoteId = $this->quoteIdMaskFactory->create();
            $maskedQuoteId->setQuoteId(
                $quote->getId()
            )->save();
            $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute(
                (int) $quote->getId()
            );
        }
        return $maskedQuoteId;
    }

    /**
     * Return Adyen Payment Methods response for given quote
     *
     * @param CartInterface $quote
     * @param ProductInterface $product
     * @return AdyenPaymentMethodsInterface
     */
    private function getAdyenPaymentMethods(
        CartInterface $quote,
        ProductInterface $product
    ): AdyenPaymentMethodsInterface {
        /** @var AdyenPaymentMethodsInterface $adyenPaymentMethods */
        $adyenPaymentMethods = $this->adyenPaymentMethods->create();
        if ((int)$quote->getItemsCount() > 0) {
            $retrievedAdyenPaymentMethods = $quote->getId() ?
                json_decode(
                    $this->adyenPaymentMethodManagement->getPaymentMethods(
                        $quote->getId()
                    ),
                    true
                ) : [];
        } else {
            $retrievedAdyenPaymentMethods = $this->getAdyenPaymentMethodsByProduct->execute(
                $product,
                $quote
            );
        }
        $extraDetails = is_array($retrievedAdyenPaymentMethods['paymentMethodsExtraDetails']) ?
            $retrievedAdyenPaymentMethods['paymentMethodsExtraDetails'] :
            [];
        $extraDetailObjects = [];
        foreach ($extraDetails as $method => $extraDetail) {
            $extraDetailObjects[] = $this->getExtraDetailsFromMethodData(
                $extraDetail,
                $method
            );
        }
        $response = is_array($retrievedAdyenPaymentMethods['paymentMethodsResponse']) ?
            $retrievedAdyenPaymentMethods['paymentMethodsResponse']['paymentMethods'] :
            [];
        $responseObjects = array_map(
            [$this, 'getMethodsResponseFromMethodData'],
            $response
        );
        $adyenPaymentMethods->setExtraDetails($extraDetailObjects);
        $adyenPaymentMethods->setMethodsResponse($responseObjects);
        return $adyenPaymentMethods;
    }

    /**
     * Build ExtraDetail Object from Payment Methods Data
     *
     * @param array $methodExtraDetailData
     * @param string $method
     * @return ExtraDetailInterface
     */
    private function getExtraDetailsFromMethodData(
        array $methodExtraDetailData,
        string $method
    ): ExtraDetailInterface {
        $isOpenInvoice = isset($methodExtraDetailData['isOpenInvoice']) && (bool)$methodExtraDetailData['isOpenInvoice'];
        $icon = $this->adyenPaymentMethodExtraDetailIcon->create(
            ['data' => $methodExtraDetailData['icon'] ?? []]
        );
        $configurationAmountData = $methodExtraDetailData['configuration']['amount'] ?? [];
        /** @var AmountInterface $amount */
        $amount = $this->adyenPaymentMethodConfigurationAmount->create(
            ['data' => $configurationAmountData]
        );
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->adyenPaymentMethodExtraDetailConfiguration->create();
        $configuration->setAmount($amount);
        $configuration->setCurrency($methodExtraDetailData['configuration']['currency'] ?? '');
        /** @var ExtraDetailInterface $extraDetail */
        $extraDetail = $this->adyenPaymentMethodExtraDetail->create();
        $extraDetail->setConfiguration($configuration);
        $extraDetail->setIcon($icon);
        $extraDetail->setMethod($method);
        $extraDetail->setIsOpenInvoice($isOpenInvoice);
        return $extraDetail;
    }

    /**
     * Build MethodResponse Object from Payment Methods Data
     *
     * @param array $methodResponseData
     * @return MethodResponseInterface
     */
    private function getMethodsResponseFromMethodData(
        array $methodResponseData
    ): MethodResponseInterface {
        /** @var MethodResponseConfigurationInterface $methodResponseConfigurationObject */
        $methodResponseConfigurationObject = $this->adyenPaymentMethodResponseConfiguration->create();
        $configurationData = $methodResponseData['configuration'] ?? [];
        $methodResponseConfigurationObject->setMerchantId($configurationData['merchantId'] ?? '');
        $methodResponseConfigurationObject->setGatewayMerchantId($configurationData['gatewayMerchantId'] ?? '');
        $methodResponseConfigurationObject->setIntent($configurationData['intent'] ?? '');
        $methodResponseConfigurationObject->setMerchantName($configurationData['merchantName'] ?? '');
        $brands = $methodResponseData['brands'] ?? [];
        $brands = is_array($brands) ?
            $brands :
            [];
        /** @var MethodResponseInterface $methodResponseObject */
        $methodResponseObject = $this->adyenPaymentMethodResponse->create();
        $methodResponseObject->setConfiguration($methodResponseConfigurationObject);
        $methodResponseObject->getName($methodResponseData['name'] ?? '');
        $methodResponseObject->setType($methodResponseData['type'] ?? '');
        $methodResponseObject->setBrands($brands);
        return $methodResponseObject;
    }
}
