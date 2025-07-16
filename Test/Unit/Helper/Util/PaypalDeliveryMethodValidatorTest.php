<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Helper\Util;

use Adyen\ExpressCheckout\Helper\Util\PaypalDeliveryMethodValidator;
use Magento\Framework\Exception\ValidatorException;
use PHPUnit\Framework\TestCase;

class PaypalDeliveryMethodValidatorTest extends TestCase
{
    private PaypalDeliveryMethodValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PaypalDeliveryMethodValidator();
    }

    public function testValidDeliveryMethod(): void
    {
        $validInput = [
            'reference' => '1',
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => [
                'currency' => 'MXN',
                'value' => 500
            ],
            'selected' => '1'
        ];

        $result = $this->validator->getValidatedDeliveryMethod($validInput);
        $this->assertSame($validInput, $result);
    }

    public function testEmptyDeliveryMethodThrowsException(): void
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Shipping methods are missing.');

        $this->validator->getValidatedDeliveryMethod([]);
    }

    public function testMissingFieldThrowsException(): void
    {
        $input = [
            'reference' => '1',
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => [
                'currency' => 'MXN',
                'value' => 500
            ]
            // 'selected' is missing
        ];

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage("Missing required delivery method field: 'selected'");

        $this->validator->getValidatedDeliveryMethod($input);
    }

    public function testNullFieldThrowsException(): void
    {
        $input = [
            'reference' => null,
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => [
                'currency' => 'MXN',
                'value' => 500
            ],
            'selected' => '1'
        ];

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage("Delivery method field 'reference' cannot be empty or null.");

        $this->validator->getValidatedDeliveryMethod($input);
    }
}
