<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data\ExtraDetail;

interface IconInterface
{
    public const URL = 'url';
    public const WIDTH = 'width';
    public const HEIGHT = 'height';

    /**
     * Get Icon Width
     *
     * @return int
     */
    public function getWidth(): int;

    /**
     * Set Icon Width
     *
     * @param int
     * @return void
     */
    public function setWidth(
        int $width
    ): void;

    /**
     * Get Icon Height
     *
     * @return int
     */
    public function getHeight(): int;

    /**
     * Set Icon Height
     *
     * @param int
     * @return void
     */
    public function setHeight(
        int $height
    ): void;

    /**
     * Get Icon URL
     *
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * Set Icon URL
     *
     * @param string
     * @return void
     */
    public function setUrl(
        string $url
    ): void;
}
