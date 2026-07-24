<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;

class CorrelationIdProviderTest extends TestCase
{
    public function testIdIsStableWithinTheSameInstance(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturn('cafebabedeadbeef');
        $provider = new CorrelationIdProvider($random);

        $this->assertSame('gtsbz-cafebabedeadbeef', $provider->get());
        $this->assertSame('gtsbz-cafebabedeadbeef', $provider->get());
    }

    public function testResetGeneratesNewId(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturnOnConsecutiveCalls('aaaa', 'bbbb');
        $provider = new CorrelationIdProvider($random);

        $first = $provider->get();
        $provider->reset();
        $second = $provider->get();

        $this->assertNotSame($first, $second);
        $this->assertSame('gtsbz-aaaa', $first);
        $this->assertSame('gtsbz-bbbb', $second);
    }

    public function testIdCarriesIdentifyingPrefix(): void
    {
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturn('xxxxxxxxxxxxxxxx');
        $provider = new CorrelationIdProvider($random);
        $this->assertStringStartsWith('gtsbz-', $provider->get());
    }

    public function testPersistedIdCanBeRestoredForCronContinuation(): void
    {
        $provider = new CorrelationIdProvider($this->createMock(Random::class));
        $provider->set('gtsbz-persisted');

        $this->assertSame('gtsbz-persisted', $provider->get());
    }
}
