<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Resolver;

use Adyen\ExpressCheckout\Model\ExpressQuoteCreator;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Cart\AddProductsToCart;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface;

class AdyenExpressInitResolver implements ResolverInterface
{
    /**
     * @param ExpressQuoteCreator $expressQuoteCreator
     * @param AddProductsToCart $addProductsToCart
     * @param CartItemFactory $cartItemFactory
     * @param PrecursorInterface $cartItemPrecursor
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly ExpressQuoteCreator $expressQuoteCreator,
        private readonly AddProductsToCart $addProductsToCart,
        private readonly CartItemFactory $cartItemFactory,
        private readonly PrecursorInterface $cartItemPrecursor,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        if (empty($args['input']['cartItem'])) {
            throw new GraphQlInputException(__('Required parameter "cartItem" is missing!'));
        }

        try {
            $maskedQuoteId = $this->expressQuoteCreator->execute($context);

            $cartItems = [];
            $cartItemsData = $this->cartItemPrecursor->process([$args['input']['cartItem']], $context);
            foreach ($cartItemsData as $cartItemData) {
                $cartItems[] = $this->cartItemFactory->create($cartItemData);
            }

            $addProductsToCartOutput = $this->addProductsToCart->execute($maskedQuoteId, $cartItems);
        } catch (Exception $e) {
            $errorMessage = "An error occurred while initiating the express quote";
            $logMessage = sprintf("%s: %s", $errorMessage, $e->getMessage());
            $this->adyenLogger->error($logMessage);

            throw new LocalizedException(__($errorMessage));
        }

        return [
            'cart' => [
                'model' => $addProductsToCartOutput->getCart(),
            ],
            'user_errors' => array_map(
                function (Error $error) {
                    return [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                        'path' => [$error->getCartItemPosition()]
                    ];
                },
                array_merge($addProductsToCartOutput->getErrors(), $this->cartItemPrecursor->getErrors())
            )
        ];
    }
}
