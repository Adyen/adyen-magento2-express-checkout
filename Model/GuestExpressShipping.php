<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressShippingInterface;
use Adyen\ExpressCheckout\Api\GuestExpressShippingInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestExpressShipping implements GuestExpressShippingInterface
{
    /**
     * @var ExpressShippingInterface
     */
    private $expressShipping;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @param ExpressShippingInterface $expressShipping
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ExpressShippingInterface $expressShipping,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->expressShipping = $expressShipping;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Update Express Shipping Method & Carrier Code
     *
     * @param string $maskedQuoteId
     * @param string $shippingMethodCode
     * @param string $shippingCarrierCode
     * @throws NoSuchEntityException
     */
    public function execute(
        string $maskedQuoteId,
        string $shippingMethodCode,
        string $shippingCarrierCode
    ): void {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load(
            $maskedQuoteId,
            'masked_id'
        );
        $this->expressShipping->execute(
            (int) $quoteIdMask->getQuoteId(),
            $shippingMethodCode,
            $shippingCarrierCode
        );
    }
}
