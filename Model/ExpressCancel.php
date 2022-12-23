<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressCancelInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class ExpressCancel implements ExpressCancelInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CartRepositoryInterface $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Set Adyen quote to inactive check for original quote and set it to active
     *
     * @param int $adyenCartId
     * @throws NoSuchEntityException
     */
    public function execute(int $adyenCartId): void
    {
        $adyenQuote = $this->cartRepository->get($adyenCartId);
        if ($adyenQuote->getIsActive()) {
            $adyenQuote->setIsActive(false);
            $this->cartRepository->save($adyenQuote);
        }
        $originalQuoteId = $adyenQuote->getAdyenOgQuoteId();
        if ($originalQuoteId) {
            try {
                $originalQuote = $this->cartRepository->get($originalQuoteId);
            } catch (NoSuchEntityException $e) {
                $originalQuote = null;
            }
            if ($originalQuote !== null &&
                !$originalQuote->getIsActive()) {
                $originalQuote->setIsActive(true);
                $this->cartRepository->save($originalQuote);
            }
        }
    }
}
