<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use LogicException;
use MySqlMemory\Server\Failures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Failures::class)]
#[Small]
final class FailuresTest extends TestCase
{
    public function testAssertNoneRetainsAFailureAfterItsMessageIsRead(): void
    {
        $channel = fopen('php://memory', 'w+');
        self::assertIsResource($channel);
        try {
            $failures = new Failures($channel);
            $failures->report(new LogicException("unimplemented\nexpression"));
            rewind($channel);
            try {
                $failures->assertNone();
                self::fail('The report must fail its first check.');
            } catch (RuntimeException $error) {
                self::assertSame('mysql-memory internal failure: failure LogicException: unimplemented expression', $error->getMessage());
            }
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('failure LogicException: unimplemented expression');
            $failures->assertNone();
        } finally {
            fclose($channel);
        }
    }

    public function testAssertNoneAcceptsAHealthyChannel(): void
    {
        $channel = fopen('php://memory', 'w+');
        self::assertIsResource($channel);
        try {
            (new Failures($channel))->assertNone();
            self::assertSame(0, ftell($channel));
        } finally {
            fclose($channel);
        }
    }

    public function testReportFitsInTheProcessPipe(): void
    {
        $channel = fopen('php://memory', 'w+');
        self::assertIsResource($channel);
        try {
            (new Failures($channel))->report(new LogicException(str_repeat('x', 100000)));
            rewind($channel);
            self::assertSame('failure LogicException: ' . str_repeat('x', 1024) . "\n", stream_get_contents($channel));
        } finally {
            fclose($channel);
        }
    }
}
