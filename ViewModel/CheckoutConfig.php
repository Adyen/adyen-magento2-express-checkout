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

namespace Adyen\ExpressCheckout\ViewModel;

use Adyen\Payment\Model\Ui\AdyenGenericConfigProvider;
use Adyen\Payment\Helper\Data;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutConfig implements ArgumentInterface
{
    /**
     * @var AdyenGenericConfigProvider
     */
    private $adyenGenericConfigProvider;

    /**
     * @var Data
     */
    private $adyenDataHelper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @param AdyenGenericConfigProvider $adyenGenericConfigProvider
     * @param Data $adyenDataHelper
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        AdyenGenericConfigProvider $adyenGenericConfigProvider,
        Data $adyenDataHelper,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        ManagerInterface $eventManager
    ) {
        $this->adyenGenericConfigProvider = $adyenGenericConfigProvider;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
    }

    /**
     * Return serialized Checkout Config array as JSON string
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getSerializedCheckoutConfig(): string
    {
        $checkoutConfig = [
            'storeCode' => $this->getStoreCode()
        ];
        $checkoutConfig = new DataObject(
            array_merge_recursive(
                $checkoutConfig,
                $this->adyenGenericConfigProvider->getConfig()
            )
        );
        $this->eventManager->dispatch(
            'adyen_checkout_config_before_return',
            ['checkout_config' => $checkoutConfig]
        );
        return $this->serializer->serialize($checkoutConfig->toArray());
    }

    /**
     * Return current stores' code
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Return application info values
     *
     * @return array
     */
    public function getAdyenData(): array
    {
        return $this->adyenDataHelper->buildRequestHeaders();
    }
}
