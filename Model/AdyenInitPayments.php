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

use Adyen\ExpressCheckout\Api\AdyenInitPaymentsInterface;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Adyen\Payment\Gateway\Http\TransferFactory;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class AdyenInitPayments implements AdyenInitPaymentsInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var ReturnUrlHelper
     */
    private ReturnUrlHelper $returnUrlHelper;

    /**
     * @var CheckoutStateDataValidator
     */
    private CheckoutStateDataValidator $checkoutStateDataValidator;

    /**
     * @var TransferFactory
     */
    private TransferFactory $transferFactory;

    /**
     * @var TransactionPayment
     */
    private TransactionPayment $transactionPaymentClient;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var PaymentResponseHandler
     */
    private PaymentResponseHandler $paymentResponseHandler;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Config $configHelper
     * @param ReturnUrlHelper $returnUrlHelper
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     * @param TransferFactory $transferFactory
     * @param TransactionPayment $transactionPaymentClient
     * @param Data $adyenHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        Config $configHelper,
        ReturnUrlHelper $returnUrlHelper,
        CheckoutStateDataValidator $checkoutStateDataValidator,
        TransferFactory $transferFactory,
        TransactionPayment $transactionPaymentClient,
        Data $adyenHelper,
        PaymentResponseHandler $paymentResponseHandler
    ) {
        $this->cartRepository = $cartRepository;
        $this->configHelper = $configHelper;
        $this->returnUrlHelper = $returnUrlHelper;
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->transferFactory = $transferFactory;
        $this->transactionPaymentClient = $transactionPaymentClient;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;
    }

    /**
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws ClientException
     */
    public function execute(int $adyenCartId, string $stateData): string
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->get($adyenCartId);
        // Reserve an order ID for the quote to obtain the reference and save the quote
        if (is_null($quote->getReservedOrderId())) {
            $quote = $quote->reserveOrderId();
            $this->cartRepository->save($quote);
        }

        $stateData = json_decode($stateData, true);
        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidatorException(
                __('Payments call failed because stateData was not a valid JSON!')
            );
        }
        // Validate the keys in stateData and remove invalid keys
        $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($stateData);
        $paymentsRequest = $this->buildPaymentsRequest($quote, $stateData);

        $transfer = $this->transferFactory->create([
            'body' => $paymentsRequest,
            'clientConfig' => ['storeId' => $quote->getStoreId()]
        ]);

        try {
            $response = $this->transactionPaymentClient->placeRequest($transfer);
            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse($response['resultCode'], $response['action'])
            );
        } catch (Exception $e) {
            throw new ClientException(
                __('Error with payment method, please select a different payment method!')
            );
        }
    }

    /**
     * @param Quote $quote
     * @param array $stateData
     * @return array
     */
    protected function buildPaymentsRequest(Quote $quote, array $stateData): array
    {
        $merchantReference = $quote->getReservedOrderId();
        $storeId = $quote->getStoreId();
        $returnUrl = sprintf(
            "%s?merchantReference=%s",
            $this->returnUrlHelper->getStoreReturnUrl($storeId),
            $merchantReference
        );
        $currency = $quote->getQuoteCurrencyCode();
        $cartSubtotal = $quote->getSubtotal();
        $request = [
            'amount' => [
                'currency' => $currency,
                'value' => $this->adyenHelper->formatAmount($cartSubtotal, $currency)
            ],
            'reference' => $merchantReference,
            'returnUrl' => $returnUrl,
            'merchantAccount' => $this->configHelper->getMerchantAccount($storeId),
            'channel' => self::PAYMENT_CHANNEL_WEB
        ];

        return array_merge($request, $stateData);
    }
}
