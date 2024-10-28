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

use Adyen\ExpressCheckout\Api\AdyenInitPaymentsInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Resolver\DataProvider\GetAdyenPaymentStatus;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;

class ExpressInitPaymentsResolver implements ResolverInterface
{
    /**
     * @param AdyenInitPaymentsInterface $adyenInitPaymentsApi
     * @param ValueFactory $valueFactory
     * @param QuoteIdMaskFactory $quoteMaskFactory
     * @param AdyenLogger $adyenLogger
     * @param GetAdyenPaymentStatus $adyenPaymentStatusDataProvider
     */
    public function __construct(
        public AdyenInitPaymentsInterface $adyenInitPaymentsApi,
        public ValueFactory $valueFactory,
        public QuoteIdMaskFactory $quoteMaskFactory,
        public AdyenLogger $adyenLogger,
        public GetAdyenPaymentStatus $adyenPaymentStatusDataProvider
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
        if (empty($args['stateData'])) {
            throw new GraphQlInputException(__('Required parameter "stateData" is missing!'));
        }

        if (empty($args['adyenMaskedQuoteId']) && empty($args['adyenCartId'])) {
            throw new GraphQlInputException(
                __('Either one of `adyenCartId` or `adyenMaskedQuoteId` is required!')
            );
        }

        try {
            $stateData = $args['stateData'];
            $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'] ?? null;
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

            $provider = $this->adyenInitPaymentsApi;

            $result = function () use ($stateData, $adyenMaskedQuoteId, $quoteId, $provider) {
                return $this->adyenPaymentStatusDataProvider->formatResponse(
                    json_decode($provider->execute($stateData, $quoteId, $adyenMaskedQuoteId), true)
                );
            };

            return $this->valueFactory->create($result);
        } catch (Exception $e) {
            $errorMessage = "An error occurred during initializing API call to `/payments` endpoint!";
            $logMessage = sprintf("%s: %s", $errorMessage, $e->getMessage());
            $this->adyenLogger->error($logMessage);

            throw new LocalizedException(__($errorMessage));
        }
    }
}
