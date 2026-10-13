<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;

class ExpressQuoteCreator
{
    /**
     * @param CartInterfaceFactory $quoteFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressInterfaceFactory $quoteAddressFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     */
    public function __construct(
        private readonly CartInterfaceFactory $quoteFactory,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressInterfaceFactory $quoteAddressFactory,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResourceModel $quoteIdMaskResourceModel
    ) {
    }

    /**
     * Create a new empty Adyen Express PDP quote for the GraphQL context user and return its masked ID
     *
     * @param ContextInterface $context
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(ContextInterface $context): string
    {
        /** @var Quote $adyenExpressQuote */
        $adyenExpressQuote = $this->quoteFactory->create();
        $adyenExpressQuote->setStoreId((int) $context->getExtensionAttributes()->getStore()->getId());
        // Like `expressInit`, the express quote stays inactive until `expressActivate` is called
        $adyenExpressQuote->setIsActive(false);
        $adyenExpressQuote->setAdyenExpressPdp(true);

        $customerId = (int) $context->getUserId();
        $isLoggedIn = (int) $context->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER;
        if ($isLoggedIn && $customerId) {
            $adyenExpressQuote->setCustomer($this->customerRepository->getById($customerId));
            $adyenExpressQuote->setCustomerIsGuest(false);
        } else {
            $adyenExpressQuote->setCustomerIsGuest(true);
        }

        // Empty addresses are required for the totals collector to compute the quote totals
        $adyenExpressQuote->setBillingAddress($this->quoteAddressFactory->create());
        $adyenExpressQuote->setShippingAddress($this->quoteAddressFactory->create());
        $adyenExpressQuote->getShippingAddress()->setCollectShippingRates(true);

        $this->quoteRepository->save($adyenExpressQuote);

        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $quoteIdMask->setQuoteId((int) $adyenExpressQuote->getId());
        $this->quoteIdMaskResourceModel->save($quoteIdMask);

        return (string) $quoteIdMask->getMaskedId();
    }
}
