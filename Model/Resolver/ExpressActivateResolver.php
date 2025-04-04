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
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;

class ExpressActivateResolver implements ResolverInterface
{
    /**
     * @param ExpressActivate $expressActivateApi
     * @param ValueFactory $valueFactory
     * @param QuoteIdMaskFactory $quoteMaskFactory
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        public ExpressActivate $expressActivateApi,
        public ValueFactory $valueFactory,
        public QuoteIdMaskFactory $quoteMaskFactory,
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
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): Value
    {
        if (empty($args['adyenMaskedQuoteId'])) {
            throw new GraphQlInputException(__('Required parameter "adyenMaskedQuoteId" is missing!'));
        }

        try {
            $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'];
            $adyenCartId = $args['adyenCartId'] ?? null;

            if (isset($adyenCartId)) {
                $quoteIdMask = $this->quoteMaskFactory->create()->load(
                    $adyenCartId,
                    'masked_id'
                );
                $quoteId = (int) $quoteIdMask->getQuoteId();
            } else {
                $quoteId = null;
            }

            $provider = $this->expressActivateApi;

            $result = function () use ($adyenMaskedQuoteId, $quoteId, $provider) {
                $provider->execute($adyenMaskedQuoteId, $quoteId);
                return true;
            };

            return $this->valueFactory->create($result);
        } catch (Exception $e) {
            $errorMessage = "An error occurred while activating the express quote";
            $logMessage = sprintf("%s: %s", $errorMessage, $e->getMessage());
            $this->adyenLogger->error($logMessage);

            throw new LocalizedException(__($errorMessage));
        }
    }
}
