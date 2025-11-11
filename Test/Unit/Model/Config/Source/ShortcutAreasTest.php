<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model\Config\Source;

use Adyen\ExpressCheckout\Model\Config\Source\ShortcutAreas;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class ShortcutAreasTest extends AbstractAdyenTestCase
{
    public function testToOptionArray(): void
    {
        $shortcutAreas = new ShortcutAreas();
        $this->assertEquals([
            ['value' => ShortcutAreas::PRODUCT_VIEW_VALUE, 'label' => 'Product View'],
            ['value' => ShortcutAreas::CART_PAGE_VALUE, 'label' => 'Cart Page'],
            ['value' => ShortcutAreas::MINICART_VALUE, 'label' => 'Minicart'],
            ['value' => ShortcutAreas::SHIPPING_PAGE_VALUE, 'label' => 'Shipping Page']
        ], $shortcutAreas->toOptionArray());
    }
}
