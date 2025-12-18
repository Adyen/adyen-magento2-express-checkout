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
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\PaymentMethods;
use Exception;
use Adyen\ExpressCheckout\Helper\LineItemsDataBuilder;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Adyen\Payment\Helper\Requests;
use Magento\Payment\Helper\Data as DataHelper;

class AdyenInitPayments implements AdyenInitPaymentsInterface
{
    private CartRepositoryInterface $cartRepository;
    private Config $configHelper;
    private ReturnUrlHelper $returnUrlHelper;
    private CheckoutStateDataValidator $checkoutStateDataValidator;
    private TransferFactory $transferFactory;
    private TransactionPayment $transactionPaymentClient;
    private Data $adyenHelper;
    private PaymentResponseHandler $paymentResponseHandler;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private Vault $vaultHelper;
    private UserContextInterface $userContext;
    private Requests $requestHelper;
    private PaymentMethods $paymentMethodsHelper;
    private DataHelper $dataHelper;
    private LineItemsDataBuilder $lineItemsDataBuilder;

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
     * @param PaymentMethods $paymentMethodsHelper
     * @param DataHelper $dataHelper
     * @param LineItemsDataBuilder $lineItemsDataBuilder
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
        Requests $requestHelper,
        PaymentMethods $paymentMethodsHelper,
        DataHelper $dataHelper,
        LineItemsDataBuilder $lineItemsDataBuilder
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
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->dataHelper = $dataHelper;
        $this->lineItemsDataBuilder = $lineItemsDataBuilder;
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
            $quotePayment = $quote->getPayment();
            if ($quotePayment) {
                $quotePayment->setAdditionalInformation('resultCode', $paymentsResponse['resultCode'] ?? null);
                if (!empty($paymentsResponse['pspReference'])) {
                    $quotePayment->setAdditionalInformation('pspReference', $paymentsResponse['pspReference']);
                }
                if (!empty($paymentsResponse['action'])) {
                    $quotePayment->setAdditionalInformation('action', $paymentsResponse['action']);
                }
                if (!empty($paymentsResponse['additionalData'])) {
                    $quotePayment->setAdditionalInformation('additionalData', $paymentsResponse['additionalData']);
                }
                if (!empty($paymentsResponse['details'])) {
                    $quotePayment->setAdditionalInformation('details', $paymentsResponse['details']);
                }
            }

            // Persist quote changes
            $this->cartRepository->save($quote);
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
     * @throws LocalizedException
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

        // If the payment method requires line items, include them
        $paymentMethodInstance = $this->dataHelper->getMethodInstance($paymentMethodCode);
        if ($this->paymentMethodsHelper->getRequiresLineItems($paymentMethodInstance)) {
            $requestLineItems = $this->lineItemsDataBuilder->getOpenInvoiceDataForQuote($quote);
            $request = array_merge($request, $requestLineItems);
        }

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
