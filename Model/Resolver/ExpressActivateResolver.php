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

use Adyen\ExpressCheckout\Model\ExpressActivate;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ExpressActivateResolver implements ResolverInterface
{
    public function __construct(
        public ExpressActivate $expressActivateApi,
        public ValueFactory $valueFactory
    ) { }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['adyenMaskedQuoteId'])) {
            throw new GraphQlInputException(__('Required parameter "adyenMaskedQuoteId" is missing!'));
        }

        $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'];
        $adyenCartId = $args['adyenCartId'] ?? null;

        $provider = $this->expressActivateApi;

        $result = function () use ($adyenMaskedQuoteId, $adyenCartId, $provider) {
            $provider->execute($adyenMaskedQuoteId, $adyenCartId);
            return true;
        };

        return $this->valueFactory->create($result);
    }
}
