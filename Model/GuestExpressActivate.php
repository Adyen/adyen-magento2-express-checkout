<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressActivateInterface;
use Adyen\ExpressCheckout\Api\GuestExpressActivateInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestExpressActivate implements GuestExpressActivateInterface
{
    /**
     * @var ExpressActivateInterface
     */
    private $expressActivate;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteMaskFactory;

    /**
     * @param ExpressActivateInterface $expressActivate
     * @param QuoteIdMaskFactory $quoteMaskFactory
     */
    public function __construct(
        ExpressActivateInterface $expressActivate,
        QuoteIdMaskFactory $quoteMaskFactory
    ) {
        $this->expressActivate = $expressActivate;
        $this->quoteMaskFactory = $quoteMaskFactory;
    }

    /**
     * Activate Guest Adyen Quote
     *
     * @param string $adyenMaskedQuoteId
     * @param string|null $currentMaskedQuoteId
     * @throws NoSuchEntityException
     */
    public function execute(
        string $adyenMaskedQuoteId,
        ?string $currentMaskedQuoteId = null
    ): void {
        $quoteId = null;
        if ($currentMaskedQuoteId !== null) {
            /** @var $quoteIdMask QuoteIdMask */
            $quoteIdMask = $this->quoteMaskFactory->create()->load(
                $currentMaskedQuoteId,
                'masked_id'
            );
            if ($quoteIdMask->getQuoteId()) {
                $quoteId = (int) $quoteIdMask->getQuoteId();
            }
        }
        $this->expressActivate->execute(
            $adyenMaskedQuoteId,
            $quoteId
        );
    }
}
