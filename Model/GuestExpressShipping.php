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
     * @param string $countryId
     * @param string $region
     * @param string $regionId
     * @param string $postcode
     * @param string $shippingDescription
     * @param string $shippingMethodCode
     * @param int $shippingAmount
     * @throws NoSuchEntityException
     */
    public function execute(
        string $maskedQuoteId,
        string $countryId,
        string $region,
        string $regionId,
        string $postcode,
        string $shippingDescription,
        string $shippingMethodCode,
        int $shippingAmount
    ): void {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load(
            $maskedQuoteId,
            'masked_id'
        );
        $this->expressShipping->execute(
            (int) $quoteIdMask->getQuoteId(),
            $countryId,
            $region,
            $regionId,
            $postcode,
            $shippingDescription,
            $shippingMethodCode,
            $shippingAmount
        );
    }
}
