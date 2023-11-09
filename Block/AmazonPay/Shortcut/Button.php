<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Block\AmazonPay\Shortcut;

use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Config as AdyenConfigHelper;
use Adyen\ExpressCheckout\Block\Buttons\AbstractButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
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
     * @var UrlInterface $url
     */
    private $url;

    /**
     * @var CustomerSession $customerSession
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;

    /**
     * Button constructor
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
     * @param ConfigurationInterface $configuration
     * @param AdyenConfigHelper $adyenConfigHelper
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
            $data
        );
        $this->defaultConfigProvider = $defaultConfigProvider;
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
}
