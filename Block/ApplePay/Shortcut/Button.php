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

namespace Adyen\ExpressCheckout\Block\ApplePay\Shortcut;

use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\ExpressCheckout\Block\Buttons\AbstractButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\Config;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\StoreManagerInterface;

class Button extends AbstractButton implements ShortcutInterface
{
    const PAYMENT_METHOD_VARIANT = 'applepay';

    /**
     * @var ConfigurationInterface $configuration
     */
    private ConfigurationInterface $configuration;

    /**
     * Button Constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param AdyenHelper $adyenHelper
     * @param Locale $localeHelper
     * @param Config $configHelper
     * @param ConfigurationInterface $configuration
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        UrlInterface $url,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManagerInterface,
        DefaultConfigProvider $defaultConfigProvider,
        ScopeConfigInterface $scopeConfig,
        AdyenHelper $adyenHelper,
        Locale $localeHelper,
        Config $configHelper,
        ConfigurationInterface $configuration,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $checkoutSession,
            $payment,
            $url,
            $customerSession,
            $storeManagerInterface,
            $scopeConfig,
            $adyenHelper,
            $localeHelper,
            $configHelper,
            $defaultConfigProvider,
            $data
        );

        $this->configuration = $configuration;
    }


    /**
     * @return string
     */
    public function getButtonColor(): string
    {
        return $this->configuration->getApplePayButtonColor();
    }

    public function buildConfiguration(): array
    {
        $baseConfiguration = parent::buildConfiguration();
        $variant = $this->getPaymentMethodVariant();

        $baseConfiguration["Adyen_ExpressCheckout/js/$variant/button"]['buttonColor'] = $this->getButtonColor();

        return $baseConfiguration;
    }
}
