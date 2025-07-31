<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressActivateInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote;

class ExpressActivate implements ExpressActivateInterface
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartInterfaceFactory
     */
    private CartInterfaceFactory $quoteFactory;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var Quote
     */
    private $quoteResource;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @param CartInterfaceFactory $quoteFactory
     * @param CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param Quote $quoteResoure
     * @param UserContextInterface $userContext
     */
    public function __construct(
        CartInterfaceFactory $quoteFactory,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        Quote $quoteResoure,
        UserContextInterface $userContext
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteResource = $quoteResoure;
        $this->userContext = $userContext;
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
