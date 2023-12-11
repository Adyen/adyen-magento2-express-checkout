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

use Magento\CheckoutAgreements\Model\AgreementsProviderInterface;

/**
 * Class AgreementsProvider
 *
 * @package Reflet\AdyenExpressCheckout\Model
 */
class AgreementsProvider implements AgreementsProviderInterface
{
    protected array $list = [];

    /**
     * AgreementsProvider Constructor
     *
     * @param AgreementsProviderInterface[] $list
     */
    public function __construct(
        array $list = []
    ) {
        $this->list = $list;
    }

    /**
     * @inheritDoc
     */
    public function getRequiredAgreementIds(): array
    {
        $ids = [];
        foreach ($this->list as $provider) {
            $ids = array_merge($ids, $provider->getRequiredAgreementIds());
        }

        return array_merge($ids);
    }
}
