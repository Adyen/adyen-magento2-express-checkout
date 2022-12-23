<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterface;
use Magento\Framework\DataObject;

class MethodResponse extends DataObject implements MethodResponseInterface
{
    /**
     * Get Payment Method Response Configuration
     *
     * @return ConfigurationInterface|null
     */
    public function getConfiguration(): ?ConfigurationInterface
    {
        $methodResponseConfiguration = $this->getData(self::CONFIGURATION);
        if (!$methodResponseConfiguration instanceof ConfigurationInterface) {
            $methodResponseConfiguration = null;
        }
        return $methodResponseConfiguration;
    }

    /**
     * Set Payment Method Response Configuration
     *
     * @param ConfigurationInterface $methodResponseConfiguration
     * @return void
     */
    public function setConfiguration(
        ConfigurationInterface $methodResponseConfiguration
    ): void {
        $this->setData(
            self::CONFIGURATION,
            $methodResponseConfiguration
        );
    }

    /**
     * Get Payment Method Icon width
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        $name = $this->getData(self::NAME);
        return $name ?
            (string) $name :
            null;
    }

    /**
     * Set Payment Method Icon width
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setData(
            self::NAME,
            $name
        );
    }

    /**
     * Get Payment Method Brands
     *
     * @return string[]
     */
    public function getBrands(): array
    {
        $brands = $this->getData(self::BRANDS);
        return is_array($brands) ?
            $brands :
            [];
    }

    /**
     * Set Payment Method Icon width
     *
     * @param string[] $brands
     * @return void
     */
    public function setBrands(array $brands): void
    {
        $this->setData(
            self::BRANDS,
            $brands
        );
    }

    /**
     * Get Payment Method Type
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        $type = $this->getData(self::TYPE);
        return $type ?
            (string) $type :
            null;
    }

    /**
     * Set Payment Method Type
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void
    {
        $this->setData(
            self::TYPE,
            $type
        );
    }
}
