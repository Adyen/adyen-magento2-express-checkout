<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data;

interface MethodResponseInterface
{
    public const CONFIGURATION = 'configuration';
    public const NAME = 'name';
    public const BRANDS = 'brands';
    public const TYPE = 'type';

    /**
     * Get Payment Methods Extra Details Configuration
     *
     * @return \Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface|null
     */
    public function getConfiguration(): ?\Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface;

    /**
     * Set Payment Methods Extra Details Configuration
     *
     * @param \Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface
     * @return void
     */
    public function setConfiguration(
        \Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface $configuration
    ): void;

    /**
     * Get Payment Method Name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set Payment Method Name
     *
     * @param string $name
     * @return void
     */
    public function setName(
        string $name
    ): void;

    /**
     * Get Payment Method Brands
     *
     * @return string[]
     */
    public function getBrands(): array;

    /**
     * Set Payment Method Brands
     *
     * @param string[] $brands
     * @return void
     */
    public function setBrands(
        array $brands
    ): void;

    /**
     * Get Payment Method Type
     *
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * Set Payment Method Type
     *
     * @param string $type
     * @return void
     */
    public function setType(
        string $type
    ): void;
}
