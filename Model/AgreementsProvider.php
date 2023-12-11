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

namespace Adyen\ExpressCheckout\Model;

use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;

/**
 * Class AgreementsProvider
 *
 * @package Reflet\AdyenExpressCheckout\Model
 */
class AgreementsProvider
{
    /**
     * @var AgreementsConfigProvider
     */
    protected $provider;

    /**
     * AgreementsProvider Constructor
     *
     * @param AgreementsConfigProvider $agreementsConfigProvider
     */
    public function __construct(
        AgreementsConfigProvider $agreementsConfigProvider
    ) {
        $this->provider = $agreementsConfigProvider;
    }

    /**
     * @return array
     */
    public function getAgreementIds(): array
    {
        $checkoutAgreements = $this->provider->getConfig();
        $agreements = $checkoutAgreements['checkoutAgreements']['agreements'] ?? null;
        if (!is_array($agreements)) {
            return [];
        }

        $ids = [];
        foreach ($agreements as $agreement) {
            $id = $agreement['agreementId'] ?? null;
            if ($id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
