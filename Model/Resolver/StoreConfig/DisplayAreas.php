<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Resolver\StoreConfig;

use Adyen\ExpressCheckout\Model\Config\Source\ShortcutAreas;
use Adyen\ExpressCheckout\Model\Configuration;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class DisplayAreas implements ResolverInterface
{
    protected const AREA_MAPPING = [
        ShortcutAreas::CART_PAGE_VALUE => 'CART_PAGE',
        ShortcutAreas::MINICART_VALUE => 'MINI_CART',
        ShortcutAreas::PRODUCT_VIEW_VALUE => 'PRODUCT_PAGE'
    ];

    /**
     * DisplayAreas Constructor
     *
     * @param Configuration $configuration
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Configuration $configuration,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $areas = $this->configuration->getShowPaymentMethodOn(
            $this->getPaymentMethodVariant($field),
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        if ($areas === []) {
            return [];
        }

        $displayAreas = [];
        foreach ($areas as $area) {
            if (isset(self::AREA_MAPPING[$area])) {
                $displayAreas[] = self::AREA_MAPPING[$area];
            }
        }

        return $displayAreas;
    }

    /**
     * @param Field $field
     * @return string
     * @throws LocalizedException
     */
    protected function getPaymentMethodVariant(Field $field): string
    {
        /**
         * Extract a method variant from the field name
         * adyen_express_paypal_express_display_areas => paypal_express
         */
        preg_match('/^adyen_express_([a-zA-Z_]+)_display_areas$/', $field->getName(), $matches);
        $variant = $matches[1] ?? null;
        if ($variant === null) {
            throw new LocalizedException(__("Invalid variant."));
        }

        return $variant;
    }
}
