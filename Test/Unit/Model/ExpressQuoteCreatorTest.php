<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\ExpressQuoteCreator;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\GraphQl\Model\Query\ContextExtensionInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ExpressQuoteCreatorTest extends AbstractAdyenTestCase
{
    private const STORE_ID = 1;
    private const QUOTE_ID = 1001;
    private const CUSTOMER_ID = 42;
    private const MASKED_QUOTE_ID = 'mock_masked_quote_id';

    private CartInterfaceFactory&MockObject $quoteFactory;
    private CartRepositoryInterface&MockObject $quoteRepository;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private AddressInterfaceFactory&MockObject $quoteAddressFactory;
    private QuoteIdMaskFactory&MockObject $quoteIdMaskFactory;
    private QuoteIdMaskResourceModel&MockObject $quoteIdMaskResourceModel;
    private Quote&MockObject $quote;
    private QuoteIdMask&MockObject $quoteIdMask;

    protected function setUp(): void
    {
        $this->quote = $this->createMockWithMethods(
            Quote::class,
            [
                'setStoreId',
                'setIsActive',
                'setCustomer',
                'setBillingAddress',
                'setShippingAddress',
                'getShippingAddress',
                'setCustomerIsGuest',
                'getId'
            ],
            ['setAdyenExpressPdp']
        );
        $this->quote->method('getId')->willReturn(self::QUOTE_ID);
        $this->quote->method('getShippingAddress')->willReturn(
            $this->createMockWithMethods(Address::class, [], ['setCollectShippingRates'])
        );

        $this->quoteFactory = $this->createGeneratedMock(CartInterfaceFactory::class, ['create']);
        $this->quoteFactory->method('create')->willReturn($this->quote);

        $this->quoteAddressFactory = $this->createGeneratedMock(AddressInterfaceFactory::class, ['create']);
        $this->quoteAddressFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(Address::class)
        );

        $this->quoteIdMask = $this->createMockWithMethods(
            QuoteIdMask::class,
            [],
            ['setQuoteId', 'getMaskedId']
        );
        $this->quoteIdMask->method('getMaskedId')->willReturn(self::MASKED_QUOTE_ID);

        $this->quoteIdMaskFactory = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $this->quoteIdMaskFactory->method('create')->willReturn($this->quoteIdMask);

        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->quoteIdMaskResourceModel = $this->createMock(QuoteIdMaskResourceModel::class);
    }

    private function createSut(): ExpressQuoteCreator
    {
        return new ExpressQuoteCreator(
            $this->quoteFactory,
            $this->quoteRepository,
            $this->customerRepository,
            $this->quoteAddressFactory,
            $this->quoteIdMaskFactory,
            $this->quoteIdMaskResourceModel
        );
    }

    private function createContext(?int $userType, ?int $userId): ContextInterface&MockObject
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);

        $extensionAttributes = $this->createGeneratedMock(ContextExtensionInterface::class, ['getStore']);
        $extensionAttributes->method('getStore')->willReturn($store);

        $context = $this->createMock(ContextInterface::class);
        $context->method('getUserType')->willReturn($userType);
        $context->method('getUserId')->willReturn($userId);
        $context->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $context;
    }

    public function testExecute_GuestUser_CreatesGuestExpressQuote(): void
    {
        $this->quote->expects($this->once())->method('setStoreId')->with(self::STORE_ID);
        $this->quote->expects($this->once())->method('setIsActive')->with(false);
        $this->quote->expects($this->once())->method('setAdyenExpressPdp')->with(true);
        $this->quote->expects($this->once())->method('setCustomerIsGuest')->with(true);
        $this->quote->expects($this->never())->method('setCustomer');
        $this->quote->expects($this->once())->method('setBillingAddress');
        $this->quote->expects($this->once())->method('setShippingAddress');

        $this->customerRepository->expects($this->never())->method('getById');
        $this->quoteRepository->expects($this->once())->method('save')->with($this->quote);
        $this->quoteIdMask->expects($this->once())->method('setQuoteId')->with(self::QUOTE_ID);
        $this->quoteIdMaskResourceModel->expects($this->once())->method('save')->with($this->quoteIdMask);

        $result = $this->createSut()->execute(
            $this->createContext(UserContextInterface::USER_TYPE_GUEST, null)
        );

        $this->assertSame(self::MASKED_QUOTE_ID, $result);
    }

    public function testExecute_LoggedInCustomer_AssignsCustomerToExpressQuote(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->expects($this->once())
            ->method('getById')
            ->with(self::CUSTOMER_ID)
            ->willReturn($customer);

        $this->quote->expects($this->once())->method('setCustomer')->with($customer);
        $this->quote->expects($this->once())->method('setCustomerIsGuest')->with(false);
        $this->quote->expects($this->once())->method('setAdyenExpressPdp')->with(true);
        $this->quoteRepository->expects($this->once())->method('save')->with($this->quote);

        $result = $this->createSut()->execute(
            $this->createContext(UserContextInterface::USER_TYPE_CUSTOMER, self::CUSTOMER_ID)
        );

        $this->assertSame(self::MASKED_QUOTE_ID, $result);
    }
}
