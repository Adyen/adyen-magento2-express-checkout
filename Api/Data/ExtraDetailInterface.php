<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data;

interface ExtraDetailInterface
{
    public const ICON = 'icon';
    public const CONFIGURATION = 'configuration';
    public const METHOD = 'method';
    public const IS_OPEN_INVOICE = 'is_open_invoice';

    /**
     * Get Payment Methods Extra Details Configuration
     *
     * @return \Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface|null
     */
    public function getConfiguration(): ?\Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface;

    /**
     * Set Payment Methods Extra Details Configuration
     *
     * @param \Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface $configuration
     * @return void
     */
    public function setConfiguration(
        \Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface $configuration
    ): void;

    /**
     * Get Payment Method Icon
     *
     * @return \Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface|null
     */
    public function getIcon(): ?\Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface;

    /**
     * Set Payment Method Icon
     *
     * @param \Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface
     * @return void
     */
    public function setIcon(
        \Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface $icon
    ): void;

    /**
     * Get Payment Method Method Name
     *
     * @return string|null
     */
    public function getMethod(): ?string;

    /**
     * Set Payment Method Method Name
     *
     * @param string $icon
     * @return void
     */
    public function setMethod(
        string $icon
    ): void;

    /**
     * Get Payment Method IsOpenInvoice
     *
     * @return bool
     */
    public function getIsOpenInvoice(): bool;

    /**
     * Set Payment Method IsOpenInvoice
     *
     * @param bool $isOpenInvoice
     * @return void
     */
    public function setIsOpenInvoice(
        bool $isOpenInvoice
    ): void;
}
