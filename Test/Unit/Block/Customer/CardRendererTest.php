<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Block\Customer;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionSearchResultsInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Block\Customer\CardRenderer;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PHPUnit\Framework\TestCase;

class CardRendererTest extends TestCase
{
    public function testRecognizesCardUsedByRenewableSubscription(): void
    {
        $results = $this->createMock(SubscriptionSearchResultsInterface::class);
        $results->method('getTotalCount')->willReturn(1);
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);

        $renderer = $this->renderer($repository);
        $token = $this->token(2);

        self::assertTrue($renderer->isUsedByRenewableSubscription($token));
        self::assertStringContainsString(
            'used by a subscription',
            (string) $renderer->getDeleteConfirmationMessage($token)
        );
    }

    public function testAllowsUnusedCardDeletionWithoutSubscriptionWarning(): void
    {
        $results = $this->createMock(SubscriptionSearchResultsInterface::class);
        $results->method('getTotalCount')->willReturn(0);
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);

        $renderer = $this->renderer($repository);
        $message = (string) $renderer->getDeleteConfirmationMessage($this->token(1));

        self::assertStringContainsString('delete the card ending', $message);
        self::assertStringNotContainsString('used by a subscription', $message);
    }

    public function testUnsavedTokenDoesNotQuerySubscriptions(): void
    {
        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $repository->expects(self::never())->method('getList');

        self::assertFalse(
            $this->renderer($repository)->isUsedByRenewableSubscription($this->token(null))
        );
    }

    private function renderer(SubscriptionRepositoryInterface $repository): CardRenderer
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('create')->willReturn($criteria);

        return new CardRenderer(
            $this->createMock(Template\Context::class),
            $this->createMock(CcConfigProvider::class),
            $repository,
            $builder
        );
    }

    private function token(?int $entityId): PaymentTokenInterface
    {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getEntityId')->willReturn($entityId);
        return $token;
    }
}
