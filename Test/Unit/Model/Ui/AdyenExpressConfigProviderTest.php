<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Model\Ui\AdyenExpressConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenExpressConfigProviderTest extends AbstractAdyenTestCase
{
    protected AdyenExpressConfigProvider $adyenExpressConfigProvider;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected ConfigurationInterface|MockObject $configHelperMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->configHelperMock = $this->createMock(ConfigurationInterface::class);
        $this->adyenExpressConfigProvider = new AdyenExpressConfigProvider(
            $this->configHelperMock,
            $this->storeManagerMock
        );
    }

    public function testGetConfig(): void
    {

    }
}
