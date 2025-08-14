<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\ExpressActivate;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(ExpressActivate::class)]
final class ExpressActivateTest extends AbstractAdyenTestCase
{
    /** @var CartRepositoryInterface&MockObject */
    private $cartRepository;

    /** @var CartInterfaceFactory&MockObject */
    private $quoteFactory;

    /** @var MaskedQuoteIdToQuoteIdInterface&MockObject */
    private $maskedToId;

    /** @var QuoteResourceModel&MockObject */
    private $quoteResource;

    private ExpressActivate $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->quoteFactory   = $this->createMock(CartInterfaceFactory::class);
        $this->maskedToId     = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->quoteResource  = $this->createMock(QuoteResourceModel::class);

        $this->subject = new ExpressActivate(
            $this->quoteFactory,
            $this->cartRepository,
            $this->maskedToId,
            $this->quoteResource
        );
    }

    /** Adyen target quote (we only need setIsActive + custom getAdyenOgQuoteId) */
    private function createAdyenQuoteMock(): QuoteModel&MockObject
    {
        return $this->getMockBuilder(QuoteModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setIsActive'])
            ->addMethods(['getAdyenOgQuoteId']) // method the model calls
            ->getMock();
    }

    /** Current quote (we need getId + setIsActive) */
    private function createCurrentQuoteMock(int $id): QuoteModel&MockObject
    {
        $q = $this->getMockBuilder(QuoteModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setIsActive'])
            ->getMock();

        $q->method('getId')->willReturn($id);
        return $q;
    }

    #[Test]
    public function it_deactivates_current_cart_and_activates_adyen_quote_when_cart_id_is_provided(): void
    {
        $maskedId = 'masked-xyz';
        $resolvedId = 1001;
        $currentCartId = 777;

        $this->maskedToId->expects($this->once())
            ->method('execute')
            ->with($maskedId)
            ->willReturn($resolvedId);

        $adyenQuote = $this->createAdyenQuoteMock();
        $this->quoteFactory->expects($this->once())->method('create')->willReturn($adyenQuote);

        $this->quoteResource->expects($this->once())
            ->method('load')
            ->with($this->identicalTo($adyenQuote), $resolvedId, CartInterface::KEY_ENTITY_ID);

        $currentQuote = $this->createCurrentQuoteMock($currentCartId);

        $currentQuote->expects($this->once())->method('setIsActive')->with(false);
        $this->cartRepository->expects($this->once())->method('get')->with($currentCartId)->willReturn($currentQuote);

        // Model calls: $adyenQuote->getAdyenOgQuoteId($currentQuote->getId());
        $adyenQuote->expects($this->once())->method('getAdyenOgQuoteId')->with($currentCartId);

        $saved = [];
        $this->cartRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($q) use (&$saved) { $saved[] = $q; return $q; });

        $adyenQuote->expects($this->once())->method('setIsActive')->with(true);

        $this->subject->execute($maskedId, $currentCartId);

        $this->assertContains($currentQuote, $saved);
        $this->assertContains($adyenQuote, $saved);
        $this->assertCount(2, $saved);
    }

    #[Test]
    public function it_activates_adyen_quote_when_current_cart_not_found(): void
    {
        $maskedId = 'masked-abc';
        $resolvedId = 2002;
        $currentCartId = 888;

        $this->maskedToId->method('execute')->with($maskedId)->willReturn($resolvedId);

        $adyenQuote = $this->createAdyenQuoteMock();
        $this->quoteFactory->method('create')->willReturn($adyenQuote);

        $this->quoteResource->expects($this->once())
            ->method('load')
            ->with($this->identicalTo($adyenQuote), $resolvedId, CartInterface::KEY_ENTITY_ID);

        $this->cartRepository->method('get')->with($currentCartId)
            ->willThrowException(new NoSuchEntityException(__('no such cart')));

        $adyenQuote->expects($this->never())->method('getAdyenOgQuoteId');
        $adyenQuote->expects($this->once())->method('setIsActive')->with(true);

        $this->cartRepository->expects($this->once())->method('save')->with($adyenQuote);

        $this->subject->execute($maskedId, $currentCartId);
    }

    #[Test]
    public function it_activates_adyen_quote_when_no_current_cart_id_is_provided(): void
    {
        $maskedId = 'masked-no-cart';
        $resolvedId = 3003;

        $this->maskedToId->method('execute')->with($maskedId)->willReturn($resolvedId);

        $adyenQuote = $this->createAdyenQuoteMock();
        $this->quoteFactory->method('create')->willReturn($adyenQuote);

        $this->quoteResource->expects($this->once())
            ->method('load')
            ->with($this->identicalTo($adyenQuote), $resolvedId, CartInterface::KEY_ENTITY_ID);

        $this->cartRepository->expects($this->never())->method('get');

        $adyenQuote->expects($this->once())->method('setIsActive')->with(true);
        $this->cartRepository->expects($this->once())->method('save')->with($adyenQuote);

        $this->subject->execute($maskedId, null);
    }

    #[Test]
    public function it_throws_no_such_entity_when_masked_id_resolution_fails(): void
    {
        $this->maskedToId->method('execute')
            ->with('bad-masked')
            ->willThrowException(new NoSuchEntityException(__('missing')));

        $this->quoteFactory->expects($this->never())->method('create');
        $this->quoteResource->expects($this->never())->method('load');
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Could not find a cart with ID');

        $this->subject->execute('bad-masked', null);
    }
}
