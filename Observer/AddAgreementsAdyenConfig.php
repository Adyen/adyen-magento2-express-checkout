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

use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AddAgreementsAdyenConfig implements ObserverInterface
{
    /**
     * @var AgreementsConfigProvider
     */
    private $agreementsConfigProvider;

    /**
     * AddAgreementsAdyenConfig constructor
     *
     * @param AgreementsConfigProvider $agreementsConfigProvider
     */
    public function __construct(
        AgreementsConfigProvider $agreementsConfigProvider
    ) {
        $this->agreementsConfigProvider = $agreementsConfigProvider;
    }

    /**
     * Add Agreements data to checkout config
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $checkoutConfig = $observer->getCheckoutConfig();
        $checkoutConfig->addData($this->agreementsConfigProvider->getConfig());
    }
}
