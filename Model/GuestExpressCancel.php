<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressCancelInterface;
use Adyen\ExpressCheckout\Api\GuestExpressCancelInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;

class GuestExpressCancel implements GuestExpressCancelInterface
{
    /**
     * @param ExpressCancelInterface $expressCancel
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly ExpressCancelInterface $expressCancel,
        private readonly MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * Cancel Express Checkout Quote for Guests
     *
     * @param string $maskedQuoteId
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(
        string $maskedQuoteId
    ): void {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedQuoteId);
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf(
                'The quote with ID %s could not be found: %s',
                $maskedQuoteId,
                $e->getMessage()
            ));
            throw new LocalizedException(__('The quote with ID %1 could not be found.', $maskedQuoteId));
        }

        $this->expressCancel->execute(
            $quoteId
        );
    }
}
