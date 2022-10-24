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

namespace Adyen\ExpressCheckout\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;

/**
 * Class AddAgreementsToMinicartConfig
 * A plugin class to add agreements ids to the minicart config
 */
class AddAgreementsToMinicartConfig
{
    /**
     * @var AgreementsConfigProvider
     */
    private $agreementsConfigProvider;

    /**
     * AddAgreementsToMinicartConfig constructor
     *
     * @param AgreementsConfigProvider $agreementsConfigProvider
     */
    public function __construct(
        AgreementsConfigProvider $agreementsConfigProvider
    ) {
        $this->agreementsConfigProvider = $agreementsConfigProvider;
    }

    /**
     * Add checkoutAgreements to Minicart config
     *
     * @param Sidebar $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfig(Sidebar $subject, array $result): array
    {
        $checkoutAgreements = $this->agreementsConfigProvider->getConfig();
        if (isset($checkoutAgreements['checkoutAgreements']['agreements'])) {
            foreach ($checkoutAgreements['checkoutAgreements']['agreements'] as $agreement) {
                $result['agreementIds'][] = $agreement['agreementId'];
            }
        }
        return $result;
    }
}
