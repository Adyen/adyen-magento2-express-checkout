<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Plugin\CustomerData;

use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Magento\Catalog\Block\ShortcutButtons;
use Magento\Checkout\CustomerData\Cart as Subject;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

class Cart
{
    public const PAYMENT_METHODS_KEY = 'adyen_payment_methods';
    public const GUEST_MASKED_ID_KEY = 'guest_masked_id';

    /**
     * @var AdyenPaymentMethodManagementInterface
     */
    private $adyenPaymentMethodManagement;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $maskedQuote;

    /**
     * Cart constructor
     *
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     * @param Session $checkoutSession
     * @param QuoteIdToMaskedQuoteIdInterface $maskedQuote
     * @param LayoutInterface $layout
     */
    public function __construct(
        AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement,
        Session $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $maskedQuote,
        LayoutInterface $layout
    ) {
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
        $this->checkoutSession = $checkoutSession;
        $this->maskedQuote = $maskedQuote;
        $this->layout = $layout;
    }

    /**
     * Intercept getSectionData and add is_minicart identifier
     *
     * @param Subject $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(
        Subject $subject,
        array $result
    ): array {
        $quote = $this->checkoutSession->getQuote();
        $quoteId = $this->checkoutSession->getQuoteId();
        $paymentMethods = $quoteId ?
            json_decode(
                $this->adyenPaymentMethodManagement->getPaymentMethods($quoteId),
                true
            ) : [];
        $result[self::PAYMENT_METHODS_KEY] = $paymentMethods;
        if ($quote &&
            !$quote->getCustomerId() &&
            $quoteId != null) {
            $maskedId = $this->maskedQuote->execute((int)$quoteId);
            $result[self::GUEST_MASKED_ID_KEY] = $maskedId;
        }
        /** @var ShortcutButtons $minicartShortcutsBlock */
        $minicartShortcutsBlock = $result['extra_actions'] ?? null;
        if ($minicartShortcutsBlock !== null) {
            unset($result['extra_actions']);
            $result['extra_actions'] = $this->layout->createBlock(
                ShortcutButtons::class
            )->setData(
                'is_minicart',
                true
            )->toHtml();
        }
        return $result;
    }
}
