<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressShippingInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\Data\ShippingMethodInterfaceFactory;

class ExpressShipping implements ExpressShippingInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var ShippingMethodInterfaceFactory
     */
    private $shippingMethodFactory;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteFactory $quoteFactory
     * @param ShippingMethodInterfaceFactory $shippingMethodFactory
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        ShippingMethodInterfaceFactory $shippingMethodFactory,
        QuoteFactory $quoteFactory
    ) {
        $this->cartRepository = $cartRepository;
        $this->shippingMethodFactory = $shippingMethodFactory;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Update Express Shipping Method & Carrier Code
     *
     * @param int $adyenCartId
     * @param string $shippingMethodCode
     * @param string $shippingCarrierCode
     * @throws NoSuchEntityException
     */
    public function execute(int $adyenCartId, string $shippingMethodCode, string $shippingCarrierCode): void
    {
        // Load the quote from the cart repository
        /** @var Quote $quote */
        $quote = $this->cartRepository->get($adyenCartId);

        // Set the shipping method code and carrier code
        $quote->getShippingAddress()->setShippingMethod($shippingMethodCode);
        $quote->getShippingAddress()->setShippingCarrierCode($shippingCarrierCode);


        // Save the updated quote
        $this->cartRepository->save($quote);
    }
}
