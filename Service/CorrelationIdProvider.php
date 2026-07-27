<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use InvalidArgumentException;
use Magento\Framework\Math\Random;

/** Provides request and recurring-operation correlation IDs. */
class CorrelationIdProvider
{
    private ?string $correlationId = null;

    public function __construct(private readonly Random $random)
    {
    }

    public function get(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = 'gtsbz-' . $this->random->getRandomString(16, '0123456789abcdef');
        }
        return $this->correlationId;
    }

    public function reset(): void
    {
        $this->correlationId = null;
    }

    /** Reuses a persisted correlation ID when work continues in a later cron process. */
    public function set(string $correlationId): void
    {
        $correlationId = trim($correlationId);
        if ($correlationId === '') {
            throw new InvalidArgumentException('Correlation ID cannot be empty.');
        }
        $this->correlationId = $correlationId;
    }
}
