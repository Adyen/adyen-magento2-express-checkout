<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressActivateInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote;

class ExpressActivate implements ExpressActivateInterface
{
    private CartRepositoryInterface $cartRepository;
    private CartInterfaceFactory $quoteFactory;
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;
    private Quote $quoteResource;

    /**
     * @param CartInterfaceFactory $quoteFactory
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param Quote $quoteResoure
     */
    public function __construct(
        CartInterfaceFactory $quoteFactory,
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        Quote $quoteResoure,
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteResource = $quoteResoure;
    }

    /**
     * @param string $adyenMaskedQuoteId
     * @param int|null $adyenCartId
     * @throws NoSuchEntityException
     */
    public function execute(
        string $adyenMaskedQuoteId,
        ?int $adyenCartId = null
    ): void {
        $adyenQuoteId = $this->getAdyenQuoteId($adyenMaskedQuoteId);
        $adyenQuote = $this->quoteFactory->create();
        $this->quoteResource->load(
            $adyenQuote,
            $adyenQuoteId,
            CartInterface::KEY_ENTITY_ID
        );
        if ($adyenCartId !== null) {
            try {
                $currentQuote = $this->cartRepository->get($adyenCartId);
            } catch (NoSuchEntityException $e) {
                $currentQuote = null;
            }
            if ($currentQuote !== null) {
                $currentQuote->setIsActive(false);
                $this->cartRepository->save($currentQuote);
                $adyenQuote->getAdyenOgQuoteId($currentQuote->getId());
            }
        }
        $adyenQuote->setIsActive(true);
        $this->cartRepository->save($adyenQuote);
    }

    /**
     * Return Adyen Quote ID from Masked ID
     *
     * @param string $maskedQuoteId
     * @return int
     * @throws NoSuchEntityException
     */
    private function getAdyenQuoteId(string $maskedQuoteId): int
    {
        return $this->maskedQuoteIdToQuoteId->execute($maskedQuoteId);
    }
}
