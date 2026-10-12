<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressCancelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class ExpressCancel implements ExpressCancelInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Release the reserved order ID and, when the Adyen quote is a clone, restore the original cart
     *
     * @param int $adyenCartId
     * @throws NoSuchEntityException
     */
    public function execute(int $adyenCartId): void
    {
        $adyenQuote = $this->cartRepository->get($adyenCartId);
        $originalQuoteId = $adyenQuote->getAdyenOgQuoteId();

        $adyenQuote->setReservedOrderId(null);

        if (!$originalQuoteId) {
            $this->cartRepository->save($adyenQuote);
            return;
        }

        $adyenQuote->setIsActive(false);
        $this->cartRepository->save($adyenQuote);

        try {
            $originalQuote = $this->cartRepository->get($originalQuoteId);
        } catch (NoSuchEntityException $e) {
            $originalQuote = null;
        }
        if ($originalQuote !== null &&
            !$originalQuote->getIsActive()) {
            $originalQuote->setIsActive(true);
            $this->cartRepository->save($originalQuote);
            $this->checkoutSession->setQuoteId($originalQuoteId);
        }
    }
}
