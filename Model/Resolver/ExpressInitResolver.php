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

use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface;
use Adyen\ExpressCheckout\Model\ExpressInit;
use Adyen\ExpressCheckout\Model\ProductCartParams;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class ExpressInitResolver implements ResolverInterface
{
    /**
     * @param ExpressInit $expressInitApi
     * @param ProductCartParams $productCartParamsPrototype
     * @param ValueFactory $valueFactory
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        public ExpressInit $expressInitApi,
        public ProductCartParams $productCartParamsPrototype,
        public ValueFactory $valueFactory,
        public MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        public AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return Value
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): Value
    {
        if (empty($args['productCartParams'])) {
            throw new GraphQlInputException(__('Required parameter "productCartParams" is missing!'));
        }

        $productCartParamsDecoded = json_decode($args['productCartParams'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GraphQlInputException(__('Invalid JSON provided for "productCartParams"!'));
        }

        try {
            $productCartParams = clone $this->productCartParamsPrototype;
            $productCartParams->setData($productCartParamsDecoded);

            $adyenCartId = $args['adyenCartId'] ?? null;
            $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'] ?? null;
            $provider = $this->expressInitApi;

            if (isset($adyenCartId)) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($adyenCartId);
            } else {
                $quoteId = null;
            }

            $result = function () use ($productCartParams, $quoteId, $adyenMaskedQuoteId, $provider) {
                return $provider->execute($productCartParams, $quoteId, $adyenMaskedQuoteId);
            };

            $valueFactory = $this->valueFactory->create($result);

            if (!$valueFactory instanceof Value) {
                throw new LocalizedException(__('Resolver failed to return a valid Value object.'));
            }
            return $valueFactory;

        } catch (Exception $e) {
            $errorMessage = "An error occurred while initiating the express quote";
            $logMessage = sprintf("%s: %s", $errorMessage, $e->getMessage());
            $this->adyenLogger->error($logMessage);

            throw new LocalizedException(__($errorMessage));
        }
    }
}
