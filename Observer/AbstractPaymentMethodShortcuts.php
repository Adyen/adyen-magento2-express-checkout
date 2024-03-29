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
namespace Adyen\ExpressCheckout\Observer;

use Adyen\ExpressCheckout\Model\Config\Source\ShortcutAreas;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Magento\Catalog\Block\ShortcutButtons;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractPaymentMethodShortcuts implements ObserverInterface
{
    /**
     * @var ConfigurationInterface
     */
    private ConfigurationInterface $configuration;

    /**
     * @var ShortcutInterface
     */
    private ShortcutInterface $shortcutButton;

    /**
     * @param ConfigurationInterface $configuration
     * @param ShortcutInterface $shortcutButton
     */
    public function __construct(
        ConfigurationInterface $configuration,
        ShortcutInterface $shortcutButton
    ) {
        $this->configuration = $configuration;
        $this->shortcutButton = $shortcutButton;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $currentPageIdentifier = $this->getCurrentPageIdentifier($observer);
        $showPaymentMethodOn = $this->configuration->getShowPaymentMethodOn(
            $this->shortcutButton->getPaymentMethodVariant()
        );
        if (!in_array(
            $currentPageIdentifier,
            $showPaymentMethodOn
        )) {
            return;
        }
        /** @var ShortcutButtons $shortcutButtons */
        $shortcutButtons = $observer->getEvent()->getContainer();
        $shortcut = $shortcutButtons->getLayout()->createBlock($this->shortcutButton::class);
        $shortcut->setIsProductView((bool)$observer->getData('is_catalog_product'));
        $shortcut->setIsCart(get_class($shortcutButtons) === QuoteShortcutButtons::class);
        $shortcutButtons->addShortcut($shortcut);
    }

    /**
     * Return current page identifier to compare with config values
     *
     * @param Observer $observer
     * @return int
     */
    private function getCurrentPageIdentifier(
        Observer $observer
    ): int {
        if ($observer->getData('is_catalog_product')) {
            return ShortcutAreas::PRODUCT_VIEW_VALUE;
        }
        $shortcutsBlock = $observer->getEvent()->getContainer();
        $isMinicart = (bool) $shortcutsBlock->getData('is_minicart');
        if ($isMinicart === true) {
            return ShortcutAreas::MINICART_VALUE;
        }
        return ShortcutAreas::CART_PAGE_VALUE;
    }
}
