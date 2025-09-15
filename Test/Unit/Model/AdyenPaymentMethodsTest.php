<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterface;
use Adyen\ExpressCheckout\Model\AdyenPaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(AdyenPaymentMethods::class)]
class AdyenPaymentMethodsTest extends AbstractAdyenTestCase
{
    private AdyenPaymentMethods $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new AdyenPaymentMethods();
    }

    #[Test]
    public function get_extra_details_returns_empty_array_by_default(): void
    {
        $this->assertSame([], $this->subject->getExtraDetails());
    }

    #[Test]
    public function get_methods_response_returns_empty_array_by_default(): void
    {
        $this->assertSame([], $this->subject->getMethodsResponse());
    }

    #[Test]
    public function set_and_get_extra_details_round_trips(): void
    {
        /** @var ExtraDetailInterface&MockObject $d1 */
        $d1 = $this->createMock(ExtraDetailInterface::class);
        /** @var ExtraDetailInterface&MockObject $d2 */
        $d2 = $this->createMock(ExtraDetailInterface::class);

        $input = [$d1, $d2];

        $this->subject->setExtraDetails($input);

        $out = $this->subject->getExtraDetails();
        $this->assertSame($input, $out);
        $this->assertContainsOnlyInstancesOf(ExtraDetailInterface::class, $out);
    }

    #[Test]
    public function set_and_get_methods_response_round_trips(): void
    {
        /** @var MethodResponseInterface&MockObject $m1 */
        $m1 = $this->createMock(MethodResponseInterface::class);
        /** @var MethodResponseInterface&MockObject $m2 */
        $m2 = $this->createMock(MethodResponseInterface::class);

        $input = [$m1, $m2];

        $this->subject->setMethodsResponse($input);

        $out = $this->subject->getMethodsResponse();
        $this->assertSame($input, $out);
        $this->assertContainsOnlyInstancesOf(MethodResponseInterface::class, $out);
    }

    #[Test]
    public function get_extra_details_returns_empty_array_if_non_array_is_set_in_data_bag(): void
    {
        // Simulate foreign code putting unexpected scalar into the DataObject
        $this->subject->setData(AdyenPaymentMethodsInterface::EXTRA_DETAILS, 'oops');

        $this->assertSame([], $this->subject->getExtraDetails());
    }

    #[Test]
    public function get_methods_response_returns_empty_array_if_non_array_is_set_in_data_bag(): void
    {
        // Simulate foreign code putting unexpected scalar into the DataObject
        $this->subject->setData(AdyenPaymentMethodsInterface::METHODS_RESPONSE, 'oops');

        $this->assertSame([], $this->subject->getMethodsResponse());
    }
}
