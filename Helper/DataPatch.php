<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Helper;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class DataPatch
{
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
    }

    public function updateConfigValue(
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
}
