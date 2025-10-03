<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\ExpressCancel;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\Exception;

class ExpressCancelTest extends AbstractAdyenTestCase
{
    private CartRepositoryInterface $cartRepository;
    private CheckoutSession $checkoutSession;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
    }

    private function createSut(): ExpressCancel
    {
        return new ExpressCancel(
            $this->cartRepository,
            $this->checkoutSession
        );
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testExecute_DeactivatesAdyenQuote_ReactivatesOriginal_SetsSession(): void
    {
        $adyenCartId = 1001;
        $originalQuoteId = 2002;

        $adyenQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->addMethods(['getAdyenOgQuoteId'])
            ->getMock();
        $adyenQuote->method('getIsActive')->willReturn(true);
        $adyenQuote->expects($this->once())->method('setIsActive')->with(false);
        $adyenQuote->method('getAdyenOgQuoteId')->willReturn($originalQuoteId);

        $originalQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->getMock();
        $originalQuote->method('getIsActive')->willReturn(false);
        $originalQuote->expects($this->once())->method('setIsActive')->with(true);

        $this->cartRepository->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($adyenQuote, $originalQuote);

        $this->cartRepository->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(Quote::class));

        $this->checkoutSession->expects($this->once())
            ->method('setQuoteId')
            ->with($originalQuoteId);

        $this->createSut()->execute($adyenCartId);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testExecute_OriginalQuoteNotFound_IsHandledGracefully(): void
    {
        $adyenCartId = 1001;
        $originalQuoteId = 9999;

        $adyenQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->addMethods(['getAdyenOgQuoteId'])
            ->getMock();

        $adyenQuote->method('getIsActive')->willReturn(true);
        $adyenQuote->expects($this->once())
            ->method('setIsActive')
            ->with(false);

        $adyenQuote->method('getAdyenOgQuoteId')->willReturn($originalQuoteId);

        // First call returns Adyen quote, second throws NoSuchEntityException
        $this->cartRepository->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $adyenQuote,
                $this->throwException(new NoSuchEntityException(__('not found')))
            );

        // Adyen quote saved once; original never saved
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($adyenQuote);

        // Session not updated since original quote not found
        $this->checkoutSession->expects($this->never())
            ->method('setQuoteId');

        // Should not throw
        $this->createSut()->execute($adyenCartId);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testExecute_NoOriginalQuoteId_OnlyDeactivatesAdyenQuote(): void
    {
        $adyenCartId = 1001;

        $adyenQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->addMethods(['getAdyenOgQuoteId'])
            ->getMock();

        // Adyen quote is active, should be deactivated and saved
        $adyenQuote->method('getIsActive')->willReturn(true);
        $adyenQuote->expects($this->once())
            ->method('setIsActive')
            ->with(false);

        // No original quote id present
        $adyenQuote->method('getAdyenOgQuoteId')->willReturn(null);

        // cartRepository->get called once (for Adyen quote) and returns our mock
        $this->cartRepository->expects($this->once())
            ->method('get')
            ->willReturn($adyenQuote);

        // Save only the Adyen quote
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($adyenQuote);

        // No session change since there is no original quote
        $this->checkoutSession->expects($this->never())
            ->method('setQuoteId');

        // Execute and ensure no exceptions
        $this->createSut()->execute($adyenCartId);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testExecute_OriginalQuoteAlreadyActive_DoesNotResaveOrSetSession(): void
    {
        $adyenCartId = 1001;
        $originalQuoteId = 2002;

        // Adyen quote mock: active -> will be deactivated & saved
        $adyenQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->addMethods(['getAdyenOgQuoteId'])
            ->getMock();
        $adyenQuote->method('getIsActive')->willReturn(true);
        $adyenQuote->expects($this->once())
            ->method('setIsActive')
            ->with(false);
        $adyenQuote->method('getAdyenOgQuoteId')->willReturn($originalQuoteId);

        // Original quote mock: already active -> no changes, no save
        $originalQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->getMock();
        $originalQuote->method('getIsActive')->willReturn(true);
        $originalQuote->expects($this->never())->method('setIsActive');

        // cartRepository->get: first returns Adyen quote, second returns original quote
        $this->cartRepository->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($adyenQuote, $originalQuote);

        // Save only the Adyen quote (original is already active)
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($adyenQuote);

        // Session should NOT be updated since original is already active
        $this->checkoutSession->expects($this->never())
            ->method('setQuoteId');

        $this->createSut()->execute($adyenCartId);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testExecute_AdyenQuoteAlreadyInactive_SkipsAdyenSave_StillHandlesOriginal(): void
    {
        $adyenCartId = 1001;
        $originalQuoteId = 2002;

        // Adyen quote mock: already inactive -> should not be saved again
        $adyenQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->addMethods(['getAdyenOgQuoteId'])
            ->getMock();
        $adyenQuote->method('getIsActive')->willReturn(false);
        $adyenQuote->expects($this->never())->method('setIsActive');
        $adyenQuote->method('getAdyenOgQuoteId')->willReturn($originalQuoteId);

        // Original quote mock: inactive -> should be activated and saved
        $originalQuote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'setIsActive'])
            ->getMock();
        $originalQuote->method('getIsActive')->willReturn(false);
        $originalQuote->expects($this->once())
            ->method('setIsActive')
            ->with(true);

        // cartRepository->get: first Adyen quote, then original
        $this->cartRepository->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($adyenQuote, $originalQuote);

        // Save only the original quote (Adyen quote already inactive)
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($originalQuote);

        // Session should be switched to the original quote
        $this->checkoutSession->expects($this->once())
            ->method('setQuoteId')
            ->with($originalQuoteId);

        $this->createSut()->execute($adyenCartId);
        $this->addToAssertionCount(1);
    }
}
