<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Plugin\Checkout\Controller\Cart;

use Magento\Checkout\Controller\Cart\Add as Subject;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class Add
{
    /**
     * @var Cart
     */
    private $customerCart;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param Cart $customerCart
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        Cart $customerCart,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository
    ) {
        $this->customerCart = $customerCart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $cartRepository;
    }

    /**
     * If Customer tries to add to cart and the active quote is somehow the adyen pdp one with an OG quote associated
     * then use the OG one if its not already been converted and set it on the checkout session
     *
     * @param Subject $subject
     * @return array
     */
    public function beforeExecute(
        Subject $subject
    ) {
        $quote = $this->customerCart->getQuote();
        $originalQuoteId = (int) $quote->getAdyenOgQuoteId();
        if ((bool)$quote->getAdyenExpressPdp() === true &&
            $originalQuoteId > 0) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
            try {
                $originalQuote = $this->quoteRepository->get($originalQuoteId);
                if (!$originalQuote->getConvertedAt()) {
                    $originalQuote->setIsActive(true);
                    $this->quoteRepository->save($originalQuote);
                    $this->checkoutSession->setQuoteId($originalQuote->getId());
                }
            } catch (NoSuchEntityException $e) {
                return [];
            }
        }
        return [];
    }
}
