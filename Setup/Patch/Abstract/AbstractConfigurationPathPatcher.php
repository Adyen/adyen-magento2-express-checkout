<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Setup\Patch\Abstract;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

abstract class AbstractConfigurationPathPatcher implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach (static::REPLACE_CONFIG_PATHS as $oldConfigPath => $newConfigPath) {
            $this->updateConfigValue(
                $this->moduleDataSetup,
                $oldConfigPath,
                $newConfigPath
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function updateConfigValue(
        ModuleDataSetupInterface $setup,
        string $oldPath,
        string $newPath
    ): void {
        $config = $this->findConfig($setup, $oldPath);

        if ($config !== false) {
            $this->configWriter->save(
                $newPath,
                $config['value'],
                $config['scope'],
                $config['scope_id']
            );
        }

        $this->reinitableConfig->reinit();
    }

    private function findConfig(ModuleDataSetupInterface $setup, string $path): mixed
    {
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $path
            );

        $matchingConfigs = $connection->fetchAll($select);
        return reset($matchingConfigs);
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
