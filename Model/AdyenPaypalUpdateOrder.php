<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\ExpressCheckout\Api\AdyenPaypalUpdateOrderInterface;
use Adyen\ExpressCheckout\Helper\PaypalUpdateOrder;
use Adyen\ExpressCheckout\Helper\Util\PaypalDeliveryMethodValidator;
use Adyen\ExpressCheckout\Model\ResourceModel\PaymentResponse\Collection as AdyenPaymentResponseCollection;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class AdyenPaypalUpdateOrder implements AdyenPaypalUpdateOrderInterface
{
    /**
     * @var PaypalUpdateOrder
     */
    protected PaypalUpdateOrder $paypalUpdateOrderHelper;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var PaypalDeliveryMethodValidator
     */
    private PaypalDeliveryMethodValidator $deliveryMethodValidator;

    /**
     * @var ChargedCurrency
     */
    private ChargedCurrency $chargedCurrency;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenPaymentResponseCollection
     */
    private AdyenPaymentResponseCollection $paymentResponseCollection;

    /**
     * @param PaypalUpdateOrder $updatePaypalOrderHelper
     * @param CartRepositoryInterface $cartRepository
     * @param PaypalDeliveryMethodValidator $deliveryMethodValidator
     * @param ChargedCurrency $chargedCurrency
     * @param Data $adyenHelper
     * @param AdyenPaymentResponseCollection $paymentResponseCollection
     */
    public function __construct(
        PaypalUpdateOrder $updatePaypalOrderHelper,
        CartRepositoryInterface $cartRepository,
        PaypalDeliveryMethodValidator $deliveryMethodValidator,
        ChargedCurrency $chargedCurrency,
        Data $adyenHelper,
        AdyenPaymentResponseCollection $paymentResponseCollection
    ) {
        $this->paypalUpdateOrderHelper = $updatePaypalOrderHelper;
        $this->cartRepository = $cartRepository;
        $this->deliveryMethodValidator = $deliveryMethodValidator;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseCollection = $paymentResponseCollection;
    }

    /**
     * @param int $adyenCartId
     * @param string $paymentData
     * @param string $deliveryMethods
     * @return string
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function execute(int $adyenCartId, string $paymentData, string $deliveryMethods = ''): string
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->get($adyenCartId);
        $merchantReference = $quote->getReservedOrderId();
        $deliveryMethods = json_decode($deliveryMethods, true);
        foreach ($deliveryMethods as &$method) {
            $method['amount']['value'] = (int) $method['amount']['value'];
        }

        // Handle the case where JSON decoding fails
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON provided for delivery methods.');
        }
        if (is_null($merchantReference)) {
            throw new ValidatorException(
                __('Order ID has not been reserved!')
            );
        }

        $paymentResponse = $this->paymentResponseCollection->getPaymentResponseWithMerchantReference(
            $merchantReference
        );

        if (is_null($paymentResponse)) {
            throw new ValidatorException(
                __('Payment response couldn\'t be found!')
            );
        }

        $decodedPaymentResponse = json_decode($paymentResponse['response'], true);
        if (!isset($decodedPaymentResponse['pspReference'])) {
            throw new ValidatorException(
                __('Payment pspreference does not exist in the payment response!')
            );
        } else {
            $pspReference = $decodedPaymentResponse['pspReference'];
        }

        $storeId = $quote->getStoreId();

        //foreach loop
        //$deliveryMethods = $this->deliveryMethodValidator->getValidatedDeliveryMethods($deliveryMethods);

        $quoteAmountCurrency = $this->chargedCurrency->getQuoteAmountCurrency($quote);
        $amountCurrency = $quoteAmountCurrency->getCurrencyCode();
        $amountValue = $quoteAmountCurrency->getAmount();

        try {
            $paypalUpdateOrderService = $this->paypalUpdateOrderHelper->createAdyenUtilityApiService($storeId);
            $paypalUpdateOrderRequest = $this->paypalUpdateOrderHelper->buildPaypalUpdateOrderRequest(
                $pspReference,
                $paymentData,
                $this->adyenHelper->formatAmount($amountValue, $amountCurrency),
                $amountCurrency,
                $deliveryMethods
            );

            $this->adyenHelper->logRequest(
                $paypalUpdateOrderRequest->toArray(),
                Client::API_CHECKOUT_VERSION,
                '/paypal/updateOrder'
            );

            $paypalUpdateOrderResponse = $paypalUpdateOrderService->updatesOrderForPaypalExpressCheckout(
                $paypalUpdateOrderRequest
            );
        } catch (AdyenException $e) {
            $errorResponse['error'] = $e->getMessage();
            $errorResponse['errorCode'] = $e->getAdyenErrorCode();

            $this->adyenHelper->logResponse($errorResponse);

            throw new ValidatorException(
                __('Error with payment method, please select a different payment method.')
            );
        }

        $this->adyenHelper->logResponse($paypalUpdateOrderResponse->toArray());

        return json_encode(
            $this->paypalUpdateOrderHelper->handlePaypalUpdateOrderResponse($paypalUpdateOrderResponse)
        );
    }
}
