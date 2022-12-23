<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressCancelInterface;
use Adyen\ExpressCheckout\Api\GuestExpressCancelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestExpressCancel implements GuestExpressCancelInterface
{
    /**
     * @var ExpressCancelInterface
     */
    private $expressCancel;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteMaskFactory;

    /**
     * @param ExpressCancelInterface $expressCancel
     * @param QuoteIdMaskFactory $quoteMaskFactory
     */
    public function __construct(
        ExpressCancelInterface $expressCancel,
        QuoteIdMaskFactory $quoteMaskFactory
    ) {
        $this->expressCancel = $expressCancel;
        $this->quoteMaskFactory = $quoteMaskFactory;
    }

    /**
     * Cancel Express Checkout Quote for Guests
     *
     * @param string $maskedQuoteId
     */
    public function execute(
        string $maskedQuoteId
    ): void {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteMaskFactory->create()->load(
            $maskedQuoteId,
            'masked_id'
        );
        $this->expressCancel->execute(
            (int) $quoteIdMask->getQuoteId()
        );
    }
}
