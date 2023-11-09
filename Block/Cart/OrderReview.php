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

namespace Adyen\ExpressCheckout\Block\Cart;

use Adyen\Payment\Helper\Config;
use Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\View\Element\Template;

class OrderReview extends Template
{
    protected $request;
    protected $configHelper;
    protected $storeManager;

    public function __construct(
        Template\Context $context,
        Config $configHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    public function isVisible(): bool
    {
        $url = $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);
        $url_parts = parse_url($url);

        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query);

            return isset($query['amazonCheckoutSessionId']);
        }

        return false;
    }

    public function getReturnUrl(): ?string
    {
        $storeId = $this->storeManager->getStore()->getId();
        $returnUrl = $this->configHelper->getConfigData(
            'return_path',
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );

        return $returnUrl;
    }
}
