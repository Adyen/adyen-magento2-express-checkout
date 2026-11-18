<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Resolver;

use Adyen\ExpressCheckout\Model\GetAdyenPaymentMethodsByProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\Store;

class PaymentMethodsByProduct implements ResolverInterface
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param QuoteFactory $quoteFactory
     * @param GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct
     */
    public function __construct(
        public ProductRepositoryInterface $productRepository,
        public QuoteFactory $quoteFactory,
        public GetAdyenPaymentMethodsByProductInterface $getAdyenPaymentMethodsByProduct,
    ) {
    }

    /**
     * @param ContextInterface $context
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $sku = (string) ($args['input']['sku'] ?? '');
        if ($sku === '') {
            throw new GraphQlInputException(__('Required parameter "sku" is missing.'));
        }

        /** @var Store $store */
        $store = $context->getExtensionAttributes()->getStore();

        try {
            $product = $this->productRepository->get($sku, false, (int) $store->getId());
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Product with SKU "%1" was not found.', $sku),
                $exception
            );
        }

        $quote = $this->buildInMemoryQuote($store);

        return $this->getAdyenPaymentMethodsByProduct->execute($product, $quote);
    }

    /**
     * Build a non-persisted quote, used only to carry store and currency context to the Adyen model.
     *
     * Currency codes are normally populated by Quote::beforeSave; we set them here since the quote is never saved.
     *
     * @param Store $store
     * @return Quote
     */
    protected function buildInMemoryQuote(Store $store): Quote
    {
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId((int) $store->getId());
        $quote->setBaseCurrencyCode($store->getBaseCurrencyCode());
        $quote->setQuoteCurrencyCode($store->getCurrentCurrencyCode());

        return $quote;
    }
}
