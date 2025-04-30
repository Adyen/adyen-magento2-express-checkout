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
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilder;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Adyen\Payment\Helper\Requests;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;

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
     * @var QuoteIdMaskFactory
     */
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @var Vault
     */
    private Vault $vaultHelper;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @var Requests
     */
    private Requests $requestHelper;

    private const FRONTEND_TYPE = 'default';

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Config $configHelper
     * @param ReturnUrlHelper $returnUrlHelper
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     * @param TransferFactory $transferFactory
     * @param TransactionPayment $transactionPaymentClient
     * @param Data $adyenHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param Vault $vaultHelper
     * @param UserContextInterface $userContext
     * @param Requests $requestHelper
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        Config $configHelper,
        ReturnUrlHelper $returnUrlHelper,
        CheckoutStateDataValidator $checkoutStateDataValidator,
        TransferFactory $transferFactory,
        TransactionPayment $transactionPaymentClient,
        Data $adyenHelper,
        PaymentResponseHandler $paymentResponseHandler,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        Vault $vaultHelper,
        UserContextInterface $userContext,
        Requests $requestHelper
    ) {
        $this->cartRepository = $cartRepository;
        $this->configHelper = $configHelper;
        $this->returnUrlHelper = $returnUrlHelper;
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->transferFactory = $transferFactory;
        $this->transactionPaymentClient = $transactionPaymentClient;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->vaultHelper = $vaultHelper;
        $this->userContext = $userContext;
        $this->requestHelper = $requestHelper;
    }

    /**
     * @param string $stateData
     * @param int|null $adyenCartId
     * @param string|null $adyenMaskedQuoteId
     * @return string
     * @throws ClientException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws LocalizedException
     */
    public function execute(
        string $stateData,
        ?int $adyenCartId = null,
        ?string $adyenMaskedQuoteId = null
    ): string {
        if (is_null($adyenCartId)) {
            /** @var $quoteIdMask QuoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load(
                $adyenMaskedQuoteId,
                'masked_id'
            );
            $adyenCartId = (int) $quoteIdMask->getQuoteId();
        }

        $quote = $this->cartRepository->get($adyenCartId);

        // Reserve an order ID for the quote to obtain the reference and save the quote
        $quote->reserveOrderId();
        $this->cartRepository->save($quote);

        $stateData = json_decode($stateData, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidatorException(
                __('Payments call failed because stateData was not a valid JSON!')
            );
        }

        // Validate payment method
        if (!isset($stateData['paymentMethod']['type']) || $stateData['paymentMethod']['type'] !== self::PAYPAL) {
            throw new ValidatorException(
                __('Error with payment method please select different payment method.')
            );
        }

        $headers = $this->adyenHelper->buildRequestHeaders();
        $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = self::FRONTEND_TYPE;

        // Validate the keys in stateData and remove invalid keys
        $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($stateData);
        $paymentsRequest = $this->buildPaymentsRequest($quote, $stateData);
        $transfer = $this->transferFactory->create([
            'body' => $paymentsRequest,
            'clientConfig' => ['storeId' => $quote->getStoreId()],
            'headers' => $headers
        ]);
        try {
            $response = $this->transactionPaymentClient->placeRequest($transfer);
            $paymentsResponse = $this->returnFirstTransactionPaymentResponse($response);

            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse($paymentsResponse['resultCode'], $paymentsResponse['action'])
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
        $amount = $quote->getSubtotalWithDiscount();
        $paymentMethodCode = "adyen_{$stateData['paymentMethod']['type']}";
        $userType = $this->userContext->getUserType();
        $customerId = $this->userContext->getUserId();
        $isLoggedIn = $userType === UserContextInterface::USER_TYPE_CUSTOMER;

        $request = [
            'amount' => [
                'currency' => $currency,
                'value' => $this->adyenHelper->formatAmount($amount, $currency)
            ],
            'reference' => $merchantReference,
            'returnUrl' => $returnUrl,
            'merchantAccount' => $this->configHelper->getMerchantAccount($storeId),
            'channel' => self::PAYMENT_CHANNEL_WEB
        ];

        // Only add these parameters if the user is logged in
        if ($isLoggedIn) {
            $isRecurringEnabled = $this->vaultHelper->getPaymentMethodRecurringActive(
                $paymentMethodCode,
                $storeId
            );

            $shopperReference = $this->requestHelper->getShopperReference($customerId, null);

            $request = array_merge($request, [
                'storePaymentMethod' => $isRecurringEnabled,
                'shopperReference' => $shopperReference,
                'shopperInteraction' => 'Ecommerce'
            ]);

            if($isRecurringEnabled)
            {
                $recurringProcessingModel = $this->vaultHelper->getPaymentMethodRecurringProcessingModel(
                    $paymentMethodCode,
                    $storeId
                );

                $request = array_merge($request, [
                    'recurringProcessingModel' => $recurringProcessingModel
                    ]);
            }
        }

        return array_merge($request, $stateData);
    }

    /**
     * This method cleans up the unnecessary gift card response data
     * and returns the actual `/payments` API response.
     *
     * @param array $response
     * @return array
     */
    private function returnFirstTransactionPaymentResponse(array $response): array
    {
        if (array_key_exists('hasOnlyGiftCards', $response)) {
            unset($response['hasOnlyGiftCards']);
        }

        return reset($response);
    }
}
