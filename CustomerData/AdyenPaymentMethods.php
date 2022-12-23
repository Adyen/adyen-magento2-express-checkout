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

namespace Adyen\ExpressCheckout\CustomerData;

use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\CustomerData\SectionSourceInterface;

class AdyenPaymentMethods implements SectionSourceInterface
{
    /**
     * @var AdyenPaymentMethodManagementInterface
     */
    private $adyenPaymentMethodManagement;

    /**
     * @var CheckoutSession
     */
    private $checkoutSesion;

    /**
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement,
        CheckoutSession $checkoutSession
    ) {
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
        $this->checkoutSesion = $checkoutSession;
    }

    /**
     * @return array
     */
    public function getSectionData()
    {
        $quoteId = $this->checkoutSesion->getQuoteId();
        return $quoteId ?
            json_decode(
                $this->adyenPaymentMethodManagement->getPaymentMethods($quoteId),
                true
            ) : [];
    }
}
