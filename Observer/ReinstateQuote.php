<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class ReinstateQuote implements ObserverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession
    ) {
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Reactive/Reinstate Original quotes after Adyen Express PDP transactions
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var OrderInterface|Order $order */
        $order = $observer->getOrder();
        if ($order) {
            $quoteId = $order->getQuoteId();
            try {
                $quote = $this->cartRepository->get((int)$quoteId);
            } catch (NoSuchEntityException $e) {
                $quote = null;
            }
            if ($quote !== null) {
                $isExpressPdp = (bool) $quote->getAdyenExpressPdp();
                $originalQuoteId = $quote->getAdyenOgQuoteId() ?
                    (int) $quote->getAdyenOgQuoteId() :
                    null;
                if ($isExpressPdp === true &&
                    $originalQuoteId !== null) {
                    try {
                        $originalQuote = $this->cartRepository->get($originalQuoteId);
                        if (!$originalQuote->getConvertedAt()) {
                            $originalQuote->setIsActive(true);
                            $this->cartRepository->save($originalQuote);
                            $this->checkoutSession->setQuoteId($originalQuote->getId());
                        }
                    } catch (NoSuchEntityException $e) {
                        return;
                    }
                }
            }
        }
    }
}
