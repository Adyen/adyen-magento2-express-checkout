<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Quote\Address\Total;

class ExtraDetail extends DataObject implements ExtraDetailInterface
{
    /**
     * Get Payment Method Extra Detail Method name
     *
     * @return string|null
     */
    public function getMethod(): ?string
    {
        $method = $this->getData(self::METHOD);
        return $method ?
            (string) $method :
            null;
    }

    /**
     * Set Payment Method Extra Detail Method name
     *
     * @param string $method
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->setData(
            self::METHOD,
            $method
        );
    }

    /**
     * Get Payment Method Extra Detail Icon
     *
     * @return IconInterface|null
     */
    public function getIcon(): ?IconInterface
    {
        $icon = $this->getData(self::ICON);
        if (!$icon instanceof IconInterface) {
            $icon = null;
        }
        return $icon;
    }

    /**
     * Set Totals for Express Checkout Data
     *
     * @param IconInterface $icon
     * @return void
     */
    public function setIcon(IconInterface $icon): void
    {
        $this->setData(
            self::ICON,
            $icon
        );
    }

    /**
     * Get Payment Method Extra Detail Icon
     *
     * @return ConfigurationInterface|null
     */
    public function getConfiguration(): ?ConfigurationInterface
    {
        $icon = $this->getData(self::CONFIGURATION);
        if (!$icon instanceof ConfigurationInterface) {
            $icon = null;
        }
        return $icon;
    }

    /**
     * Set Totals for Express Checkout Data
     *
     * @param ConfigurationInterface $icon
     * @return void
     */
    public function setConfiguration(
        ConfigurationInterface $icon
    ): void {
        $this->setData(
            self::CONFIGURATION,
            $icon
        );
    }
    /**
     * Get Payment Method Extra Detail Method name
     *
     * @return bool
     */
    public function getIsOpenInvoice(): bool
    {
        return (bool) $this->getData(self::IS_OPEN_INVOICE);
    }

    /**
     * Set Payment Method Extra Detail Method name
     *
     * @param bool $isOpenInvoice
     * @return void
     */
    public function setIsOpenInvoice(bool $isOpenInvoice): void
    {
        $this->setData(
            self::IS_OPEN_INVOICE,
            $isOpenInvoice
        );
    }
}
