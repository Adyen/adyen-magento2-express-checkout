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

use Adyen\ExpressCheckout\Api\AdyenPaypalUpdateOrderInterface;
use Adyen\ExpressCheckout\Helper\PaypalUpdateOrder;
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

class AdyenExpressPaypalUpdateOrderResolver implements ResolverInterface
{
    /**
     * @param AdyenPaypalUpdateOrderInterface $adyenPaypalUpdateOrderApi
     * @param ValueFactory $valueFactory
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     * @param PaypalUpdateOrder $paypalUpdateOrderHelper
     */
    public function __construct(
        public AdyenPaypalUpdateOrderInterface $adyenPaypalUpdateOrderApi,
        public ValueFactory $valueFactory,
        public MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        public AdyenLogger $adyenLogger,
        public PaypalUpdateOrder $paypalUpdateOrderHelper
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
        if (empty($args['paymentData'])) {
            throw new GraphQlInputException(__('Required parameter "paymentData" is missing!'));
        }

        if (empty($args['adyenMaskedQuoteId']) && empty($args['adyenCartId'])) {
            throw new GraphQlInputException(
                __('Either one of `adyenCartId` or `adyenMaskedQuoteId` is required!')
            );
        }

        try {
            $paymentData = $args['paymentData'];
            $deliveryMethods = $args['deliveryMethods'] ?? [];
            $adyenMaskedQuoteId = $args['adyenMaskedQuoteId'] ?? null;
            $adyenCartId = $args['adyenCartId'] ?? null;

            if (isset($adyenCartId)) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($adyenCartId);

            } else {
                $quoteId = null;
            }

            $provider = $this->adyenPaypalUpdateOrderApi;

            $result = function () use ($paymentData, $deliveryMethods, $adyenMaskedQuoteId, $quoteId, $provider) {
                return json_decode(
                    $provider->execute($paymentData, $quoteId, $adyenMaskedQuoteId, json_encode($deliveryMethods)),
                    true
                );
            };
            $valueFactory = $this->valueFactory->create($result);

            if (!$valueFactory instanceof Value) {
                throw new LocalizedException(__('Resolver failed to return a valid Value object.'));
            }
            return $valueFactory;

        } catch (Exception $e) {
            $errorMessage = "An error occurred during initializing API call to `/paypal/updateOrder` endpoint!";
            $logMessage = sprintf("%s: %s", $errorMessage, $e->getMessage());
            $this->adyenLogger->error($logMessage);

            throw new LocalizedException(__($errorMessage));
        }
    }
}
