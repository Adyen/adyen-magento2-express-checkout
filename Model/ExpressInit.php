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

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface;
use Adyen\ExpressCheckout\Api\ExpressInitInterface;
use Adyen\ExpressCheckout\Exception\ExpressInitException;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart\RequestInfoFilterInterface;
use Magento\Checkout\Model\Cart\RequestQuantityProcessor;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ExpressInit implements ExpressInitInterface
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var ExpressDataBuilderInterface
     */
    private $expressDataBuilder;

    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var LocalizedToNormalized
     */
    private $localizedToNormalizedFilter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var RequestQuantityProcessor
     */
    private $quantityProcessor;

    /**
     * @var CartInterfaceFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var RequestInfoFilterInterface
     */
    private $requestInfoFilter;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @param CartManagementInterface $cartManagement
     * @param ExpressDataBuilderInterface $expressDataBuilder
     * @param ResolverInterface $localeResolver
     * @param LocalizedToNormalized $localizedToNormalizedFilter
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param RequestQuantityProcessor $requestQuantityProcessor
     * @param CartInterfaceFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteMaskFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param RequestInfoFilterInterface $requestInfoFilter
     * @param StoreManagerInterface $storeManager
     * @param UserContextInterface $userContext
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        ExpressDataBuilderInterface $expressDataBuilder,
        ResolverInterface $localeResolver,
        LocalizedToNormalized $localizedToNormalizedFilter,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        RequestQuantityProcessor $requestQuantityProcessor,
        CartInterfaceFactory $quoteFactory,
        QuoteIdMaskFactory $quoteMaskFactory,
        CartRepositoryInterface $quoteRepository,
        RequestInfoFilterInterface $requestInfoFilter,
        StoreManagerInterface $storeManager,
        UserContextInterface $userContext,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->cartManagement = $cartManagement;
        $this->expressDataBuilder = $expressDataBuilder;
        $this->localeResolver = $localeResolver;
        $this->localizedToNormalizedFilter = $localizedToNormalizedFilter;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->quantityProcessor = $requestQuantityProcessor;
        $this->quoteFactory = $quoteFactory;
        $this->quoteMaskFactory = $quoteMaskFactory;
        $this->quoteRepository = $quoteRepository;
        $this->requestInfoFilter = $requestInfoFilter;
        $this->storeManager = $storeManager;
        $this->userContext = $userContext;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Initialise Express Checkout, return data to use in FE JS
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param int|null $adyenCartId
     * @param string|null $adyenMaskedQuoteId
     * @return ExpressDataInterface|null
     * @throws ExpressInitException
     */
    public function execute(
        ProductCartParamsInterface $productCartParams,
        ?int $adyenCartId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?ExpressDataInterface {
        try {
            $adyenExpressQuote = $this->getAdyenExpressQuote(
                $adyenCartId,
                $adyenMaskedQuoteId
            );
            $productId = $productCartParams->getProduct();
            $product = $this->getProduct($productId);
            if ($product === null) {
                return null;
            }
            $adyenExpressQuote = $this->addProductToAdyenExpressQuote(
                $adyenExpressQuote,
                $productCartParams,
                $product
            );
            // We have to save it here to get the totals in the express data builder
            $this->quoteRepository->save($adyenExpressQuote);
            $expressData = $this->expressDataBuilder->execute(
                $adyenExpressQuote,
                $product
            );
            // Then we have to save it here again to set it as inactive
            $adyenExpressQuote->setIsActive(false);
            $this->quoteRepository->save($adyenExpressQuote);
            return $expressData;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new ExpressInitException(
                __($e->getMessage()),
                $e
            );
        }
    }

    /**
     * Try and add Product to Adyen Express Quote
     *
     * @param CartInterface $adyenExpressQuote
     * @param ProductCartParamsInterface $productCartParams
     * @param ProductInterface $product
     * @return CartInterface
     * @throws LocalizedException
     */
    private function addProductToAdyenExpressQuote(
        CartInterface $adyenExpressQuote,
        ProductCartParamsInterface $productCartParams,
        ProductInterface $product
    ): CartInterface {
        $qty = $productCartParams->getQty();
        if ($qty !== null) {
            $this->localizedToNormalizedFilter->setOptions([
                'locale' => $this->localeResolver->getLocale()
            ]);
            $processedQty = $this->quantityProcessor->prepareQuantity($qty);
            $productCartParams->setQty($processedQty);
        }
        $this->requestInfoFilter->filter($productCartParams);
        $adyenExpressQuote->setInventoryProcessed(false);
        $adyenExpressQuote->addProduct(
            $product,
            $productCartParams
        );
        $adyenExpressQuote->collectTotals();
        $adyenExpressQuote->setTotalsCollectedFlag(false);
        return $adyenExpressQuote;
    }

    /**
     * Return product if it exists
     *
     * @param int|null $productId
     * @return ProductInterface|null
     */
    private function getProduct(
        ?int $productId
    ): ?ProductInterface {
        if ($productId === null) {
            return null;
        }
        try {
            $storeId = $this->storeManager->getStore()->getId();
            return $this->productRepository->getById(
                $productId,
                false,
                $storeId
            );
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Return AdyenExpress Quote, checks current cart if its empty it will be used
     *
     * @param int|null $cartId
     * @param string|null $adyenMaskedQuoteId
     * @return CartInterface
     */
    private function getAdyenExpressQuote(
        ?int $cartId = null,
        ?string $adyenMaskedQuoteId = null
    ): CartInterface {
        $adyenExpressQuote = null;
        if ($adyenMaskedQuoteId !== null) {
            $adyenExpressQuoteId = $this->quoteMaskFactory->create()->load(
                $adyenMaskedQuoteId,
                'masked_id'
            );
            try {
                /** @var Quote $adyenExpressQuote */
                $adyenExpressQuote = $this->quoteRepository->get(
                    $adyenExpressQuoteId->getQuoteId()
                );
                if ((int)$adyenExpressQuote->getItemsCount() > 0) {
                    foreach ($adyenExpressQuote->getAllVisibleItems() ?? [] as $quoteItem) {
                        $adyenExpressQuote->deleteItem($quoteItem);
                    }
                }
            } catch (NoSuchEntityException $e) {
                $adyenExpressQuote = null;
            }
        }
        if ($cartId !== null
            && $adyenExpressQuote === null) {
            try {
                $existingQuote = $this->quoteRepository->get($cartId);
                if (!$existingQuote->getItemsCount()) {
                    $adyenExpressQuote = $existingQuote;
                }
            } catch (NoSuchEntityException $e) {
                $adyenExpressQuote = null;
            }
        }
        if ($adyenExpressQuote === null) {
            /** @var CartInterface|Quote $adyenExpressQuote */
            $adyenExpressQuote = $this->quoteFactory->create();
            $adyenExpressQuote->setStore(
                $this->storeManager->getStore()
            );
        }
        $userType = $this->userContext->getUserType();
        $customerId = $this->userContext->getUserId();
        $isLoggedIn = (int)$userType === UserContextInterface::USER_TYPE_CUSTOMER;
        if ($isLoggedIn && $customerId) {
            $customer = $this->customerRepository->getById($customerId);
            $adyenExpressQuote->setCustomerId($customerId);
            $adyenExpressQuote->setCustomerEmail($customer->getEmail());
        }
        $adyenExpressQuote->setAdyenExpressPdp(true);
        if ($cartId &&
            (int) $adyenExpressQuote->getId() !== $cartId) {
            $adyenExpressQuote->setAdyenOgQuoteId($cartId);
        }
        if (!$adyenExpressQuote->getIsActive()) {
            $adyenExpressQuote->setIsActive(true);
        }
        return $adyenExpressQuote;
    }
}
