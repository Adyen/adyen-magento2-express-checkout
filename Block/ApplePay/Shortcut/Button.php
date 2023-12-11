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

use Adyen\ExpressCheckout\Model\AgreementsProvider;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Config as AdyenConfigHelper;
use Adyen\ExpressCheckout\Block\Buttons\AbstractButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Button extends AbstractButton implements ShortcutInterface
{
    /**
     * @var DefaultConfigProvider $defaultConfigProvider
     */
    private $defaultConfigProvider;

    /**
     * @var ConfigurationInterface $configuration
     */
    private $configuration;

    /**
     * Button Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Model\MethodInterface $payment
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Checkout\Model\DefaultConfigProvider $defaultConfigProvider
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Helper\Config $adyenConfigHelper
     * @param \Adyen\ExpressCheckout\Model\ConfigurationInterface $configuration
     * @param \Adyen\ExpressCheckout\Model\AgreementsProvider $agreementsProvider
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
        AdyenConfigHelper $adyenConfigHelper,
        ConfigurationInterface $configuration,
        AgreementsProvider $agreementsProvider,
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
            $adyenConfigHelper,
            $agreementsProvider,
            $data
        );
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->configuration = $configuration;
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }
        return '';
    }

    /**
     * @return string
     */
    public function getButtonColor(): string
    {
        return $this->configuration->getApplePayButtonColor();
    }
}
