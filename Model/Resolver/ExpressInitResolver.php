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

use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterfaceFactory;
use Adyen\ExpressCheckout\Model\ExpressInit;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ExpressInitResolver implements ResolverInterface
{
    /**
     * @param ExpressInit $expressInitApi
     * @param ProductCartParamsInterfaceFactory $productCartParamsFactory
     * @param ValueFactory $valueFactory
     */
    public function __construct(
        public ExpressInit $expressInitApi,
        public ProductCartParamsInterfaceFactory $productCartParamsFactory,
        public ValueFactory $valueFactory
    ) { }

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return Value
     * @throws GraphQlInputException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): Value
    {
        if (empty($args['productCartParams'])) {
            throw new GraphQlInputException(__('Required parameter "productCartParams" is missing!'));
        }

        $productCartParamsDecoded = json_decode($args['productCartParams'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GraphQlInputException(__('Invalid JSON provided for "productCartParams"!'));
        }

        $productCartParams = $this->productCartParamsFactory->create();
        $productCartParams->setData($productCartParamsDecoded);

        $adyenCartId = $args['adyenCartId'] ?? null;
        $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'] ?? null;
        $provider = $this->expressInitApi;

        $result = function () use ($productCartParams, $adyenCartId, $adyenMaskedQuoteId, $provider) {
            return $provider->execute($productCartParams, $adyenCartId, $adyenMaskedQuoteId);
        };

        return $this->valueFactory->create($result);
    }
}
