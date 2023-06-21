<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressShippingInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\Data\ShippingMethodInterfaceFactory;
use Magento\Quote\Api\Data\AddressInterface;

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
     * @var AddressInterface
     */
    private $address;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteFactory $quoteFactory
     * @param ShippingMethodInterfaceFactory $shippingMethodFactory
     * @param AddressInterface $address
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        ShippingMethodInterfaceFactory $shippingMethodFactory,
        QuoteFactory $quoteFactory,
        AddressInterface $address
    ) {
        $this->cartRepository = $cartRepository;
        $this->shippingMethodFactory = $shippingMethodFactory;
        $this->quoteFactory = $quoteFactory;
        $this->address = $address;
    }

    /**
     * Update Express Shipping Method & Carrier Code
     *
     * @param int $adyenCartId
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
        int $adyenCartId,
        string $countryId,
        string $region,
        string $regionId,
        string $postcode,
        string $shippingDescription,
        string $shippingMethodCode,
        int $shippingAmount): void
    {

        /** @var Quote $quote */
        $quote = $this->cartRepository->get($adyenCartId);
        $shippingAddress = $quote->getShippingAddress();

        // update quote_address table
        $shippingAddress->setCountryId($countryId);
        $shippingAddress->setRegion($region);
        $shippingAddress->setRegionId($regionId);
        $shippingAddress->setPostcode($postcode);
        $shippingAddress->setShippingMethod($shippingMethodCode);
        $shippingAddress->setShippingDescription($shippingDescription);
        $decimalAmount = number_format($shippingAmount, 4, '.', '');
        $shippingAddress->setShippingAmount($decimalAmount);

        $quote->setShippingAddress($shippingAddress);
        $this->cartRepository->save($quote);
        $shippingAmount = $shippingAddress->getShippingAmount();

        // recalculate totals, including the grand total
        $quote->collectTotals();

        // set the shipping amount back to shippingAddress
//        $shippingAddress->setShippingAmount($shippingAmount);
    }
}
