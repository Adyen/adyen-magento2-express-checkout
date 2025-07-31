<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressActivateInterface;
use Adyen\ExpressCheckout\Api\GuestExpressActivateInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;

class GuestExpressActivate implements GuestExpressActivateInterface
{
    private ExpressActivateInterface $expressActivate;
    private MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId;

    /**
     * @param ExpressActivateInterface $expressActivate
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     */
    public function __construct(
        ExpressActivateInterface $expressActivate,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
    ) {
        $this->expressActivate = $expressActivate;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
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
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($currentMaskedQuoteId);
        }
        $this->expressActivate->execute(
            $adyenMaskedQuoteId,
            $quoteId
        );
    }
}
