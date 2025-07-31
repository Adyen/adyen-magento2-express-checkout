<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressCancelInterface;
use Adyen\ExpressCheckout\Api\GuestExpressCancelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;

class GuestExpressCancel implements GuestExpressCancelInterface
{
    private ExpressCancelInterface $expressCancel;
    private MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId;

    /**
     * @param ExpressCancelInterface $expressCancel
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     */
    public function __construct(
        ExpressCancelInterface $expressCancel,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
    ) {
        $this->expressCancel = $expressCancel;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
    }

    /**
     * Cancel Express Checkout Quote for Guests
     *
     * @param string $maskedQuoteId
     * @throws NoSuchEntityException
     */
    public function execute(
        string $maskedQuoteId
    ): void {
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedQuoteId);
        $this->expressCancel->execute(
            $quoteId
        );
    }
}
