<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Request\CommandBuilder;
use PHPUnit\Framework\TestCase;

class CommandBuilderTest extends TestCase
{
    public function testAuthorizeCommandBuildsAuthonlyTransactionCommand(): void
    {
        $this->assertSame(
            [
                'tran' => [
                    'Command' => 'authonly',
                    'IsRecurring' => false,
                    'IgnoreDuplicate' => false,
                    'CustReceipt' => false,
                ],
                '__method' => 'runTransaction',
            ],
            (new CommandBuilder('authonly'))->build([])
        );
    }

    public function testAuthorizeCaptureSaleCommandBuildsSaleTransactionCommand(): void
    {
        $this->assertSame(
            [
                'tran' => [
                    'Command' => 'sale',
                    'IsRecurring' => false,
                    'IgnoreDuplicate' => false,
                    'CustReceipt' => false,
                ],
                '__method' => 'runTransaction',
            ],
            (new CommandBuilder('sale'))->build([])
        );
    }
}
