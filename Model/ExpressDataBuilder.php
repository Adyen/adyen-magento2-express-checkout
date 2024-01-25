<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Adyen\ExpressCheckout\Api\Data\ExpressDataInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface as MethodResponseConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterfaceFactory as MethodResponseConfigurationInterfaceFactory;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterfaceFactory;
use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

class ExpressDataBuilder implements ExpressDataBuilderInterface
{
    /**
     * @var AdyenPaymentMethodsInterfaceFactory
     */
    private $adyenPaymentMethodsFactory;

    /**
     * @var AdyenPaymentMethodManagementInterface
     */
    private $adyenPaymentMethodManagement;

    /**
     * @var ExtraDetailInterfaceFactory
     */
    private $adyenPaymentMethodExtraDetailFactory;

    /**
     * @var AmountInterfaceFactory
     */
    private $adyenPaymentMethodConfigurationAmountFactory;

    /**
     * @var ConfigurationInterfaceFactory
     */
    private $adyenPaymentMethodExtraDetailConfigurationFactory;

    /**
     * @var IconInterfaceFactory
     */
    private $adyenPaymentMethodExtraDetailIconFactory;

    /**
     * @var MethodResponseInterfaceFactory
     */
    private $adyenPaymentMethodResponseFactory;

    /**
     * @var MethodResponseConfigurationInterfaceFactory
     */
    private $adyenPaymentMethodResponseConfigurationFactory;

    /**
     * @var CartTotalRepositoryInterface
     */
    private $cartTotalRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ExpressDataInterfaceFactory
     */
    private $expressDataFactory;

    /**
     * @var GetAdyenPaymentMethodsByProductInterface
     */
    private $getAdyenPaymentMethodsByProduct;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     * @param AdyenPaymentMethodsInterfaceFactory $adyenPaymentMethodsFactory
     * @param AmountInterfaceFactory $adyenPaymentMethodConfigurationAmountFactory
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param ConfigurationInterfaceFactory $adyenPaymentMethodExtraDetailConfigurationFactory
     * @param CustomerSession $customerSession
     * @param ExpressDataInterfaceFactory $expressDataFactory
     * @param ExtraDetailInterfaceFactory $adyenPaymentMethodExtraDetailFactory
     * @param GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct
     * @param IconInterfaceFactory $adyenPaymentMethodExtraDetailIconFactory
     * @param MethodResponseInterfaceFactory $adyenPaymentMethodResponseFactory
     * @param MethodResponseConfigurationInterfaceFactory $adyenPaymentMethodResponseConfigurationFactory
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement,
        AdyenPaymentMethodsInterfaceFactory $adyenPaymentMethodsFactory,
        AmountInterfaceFactory $adyenPaymentMethodConfigurationAmountFactory,
        CartTotalRepositoryInterface $cartTotalRepository,
        ConfigurationInterfaceFactory $adyenPaymentMethodExtraDetailConfigurationFactory,
        CustomerSession $customerSession,
        ExpressDataInterfaceFactory $expressDataFactory,
        ExtraDetailInterfaceFactory $adyenPaymentMethodExtraDetailFactory,
        GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct,
        IconInterfaceFactory $adyenPaymentMethodExtraDetailIconFactory,
        MethodResponseInterfaceFactory $adyenPaymentMethodResponseFactory,
        MethodResponseConfigurationInterfaceFactory $adyenPaymentMethodResponseConfigurationFactory,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
        $this->adyenPaymentMethodsFactory = $adyenPaymentMethodsFactory;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->customerSession = $customerSession;
        $this->expressDataFactory = $expressDataFactory;
        $this->adyenPaymentMethodExtraDetailFactory = $adyenPaymentMethodExtraDetailFactory;
        $this->adyenPaymentMethodConfigurationAmountFactory = $adyenPaymentMethodConfigurationAmountFactory;
        $this->adyenPaymentMethodExtraDetailConfigurationFactory = $adyenPaymentMethodExtraDetailConfigurationFactory;
        $this->adyenPaymentMethodExtraDetailIconFactory = $adyenPaymentMethodExtraDetailIconFactory;
        $this->adyenPaymentMethodResponseFactory = $adyenPaymentMethodResponseFactory;
        $this->adyenPaymentMethodResponseConfigurationFactory = $adyenPaymentMethodResponseConfigurationFactory;
        $this->adyenPaymentMethodExtraDetailIconFactory = $adyenPaymentMethodExtraDetailIconFactory;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->getAdyenPaymentMethodsByProduct = $getAdyenPaymentMethodsByProduct;
    }

    /**
     * Build Express Data Object and set Data based on Quote
     *
     * @param CartInterface $quote
     * @param ProductInterface $product
     * @return ExpressDataInterface
     */
    public function execute(
        CartInterface $quote,
        ProductInterface $product
    ): ExpressDataInterface {
        /** @var ExpressDataInterface $expressData */
        $expressData = $this->expressDataFactory->create();
        /** @var AdyenPaymentMethodsInterface $adyenPaymentMethods */
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
        $adyenPaymentMethods = $this->adyenPaymentMethodsFactory->create();
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
     * @param array $retrievePaymentMethodsResponse
     * @param string $method
     * @return ExtraDetailInterface
     */
    private function getExtraDetailsFromMethodData(
        array $methodExtraDetailData,
        string $method
    ): ExtraDetailInterface {
        $isOpenInvoice = isset($methodExtraDetailData['isOpenInvoice']) ?
            (bool) $methodExtraDetailData['isOpenInvoice'] :
            false;
        $icon = $this->adyenPaymentMethodExtraDetailIconFactory->create(
            ['data' => $methodExtraDetailData['icon'] ?? []]
        );
        $configurationAmountData = $methodExtraDetailData['configuration']['amount'] ?? [];
        /** @var AmountInterface $amount */
        $amount = $this->adyenPaymentMethodConfigurationAmountFactory->create(
            ['data' => $configurationAmountData]
        );
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->adyenPaymentMethodExtraDetailConfigurationFactory->create();
        $configuration->setAmount($amount);
        $configuration->setCurrency($methodExtraDetailData['configuration']['currency'] ?? '');
        /** @var ExtraDetailInterface $extraDetail */
        $extraDetail = $this->adyenPaymentMethodExtraDetailFactory->create();
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
        $methodResponseConfigurationObject = $this->adyenPaymentMethodResponseConfigurationFactory->create();
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
        $methodResponseObject = $this->adyenPaymentMethodResponseFactory->create();
        $methodResponseObject->setConfiguration($methodResponseConfigurationObject);
        $methodResponseObject->getName($methodResponseData['name'] ?? '');
        $methodResponseObject->setType($methodResponseData['type'] ?? '');
        $methodResponseObject->setBrands($brands);
        return $methodResponseObject;
    }
}
