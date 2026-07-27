<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\DebugTrace;
use Gtstudio\Ebizcharge\Model\DebugTraceFactory;
use Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace as DebugTraceResource;
use Gtstudio\Ebizcharge\Service\DebugTraceRecorder;
use PHPUnit\Framework\TestCase;

class DebugTraceRecorderTest extends TestCase
{
    public function testRecordRedactsGatewayCredentialsBeforePersistence(): void
    {
        $config = $this->createMock(Config::class);
        $traceFactory = $this->createMock(DebugTraceFactory::class);
        $resource = $this->createMock(DebugTraceResource::class);
        $logger = $this->createMock(Logger::class);
        $trace = $this->createMock(DebugTrace::class);

        $traceFactory->expects($this->once())
            ->method('create')
            ->willReturn($trace);

        $trace->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data): bool {
                $request = json_decode((string) $data['request_summary'], true);

                self::assertSame('***REDACTED***', $request['securityToken']['UserId']);
                self::assertSame('***REDACTED***', $request['securityToken']['SecurityId']);
                self::assertSame('***REDACTED***', $request['securityToken']['Password']);
                self::assertSame('runCustomerTransaction', $request['method']);

                return true;
            }))
            ->willReturnSelf();

        $resource->expects($this->once())
            ->method('save')
            ->with($trace);

        $recorder = new DebugTraceRecorder($config, $traceFactory, $resource, $logger);
        $recorder->record(
            'correlation-id',
            'runCustomerTransaction',
            'https://sandbox.example.test',
            'authonly',
            [
                'method' => 'runCustomerTransaction',
                'securityToken' => [
                    'UserId' => 'merchant-user',
                    'SecurityId' => 'merchant-security-id',
                    'Password' => 'merchant-password',
                ],
            ],
            ['ResultCode' => 'A', 'RefNum' => '12345'],
            100
        );
    }
}
