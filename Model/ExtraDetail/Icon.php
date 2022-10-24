<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\ExtraDetail;

use Adyen\ExpressCheckout\Api\Data\ExtraDetail\IconInterface;
use Magento\Framework\DataObject;

class Icon extends DataObject implements IconInterface
{
    /**
     * Get Payment Method Icon width
     *
     * @return int
     */
    public function getWidth(): int
    {
        return (int) $this->getData(self::WIDTH);
    }

    /**
     * Set Payment Method Icon width
     *
     * @param int $width
     * @return void
     */
    public function setWidth(int $width): void
    {
        $this->setData(
            self::WIDTH,
            $width
        );
    }

    /**
     * Get Payment Method Icon width
     *
     * @return int
     */
    public function getHeight(): int
    {
        return (int) $this->getData(self::HEIGHT);
    }

    /**
     * Set Payment Method Icon width
     *
     * @param int $height
     * @return void
     */
    public function setHeight(int $height): void
    {
        $this->setData(
            self::HEIGHT,
            $height
        );
    }

    /**
     * Get Payment Method Icon width
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        $iconUrl = $this->getData(self::URL);
        return $iconUrl ?
            (string) $iconUrl :
            null;
    }

    /**
     * Set Payment Method Icon width
     *
     * @param string $iconUrl
     * @return void
     */
    public function setUrl(string $iconUrl): void
    {
        $this->setData(
            self::URL,
            $iconUrl
        );
    }
}
