<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Deriver\Model\ModelException
 */
#[CoversClass(\Deriver\Model\ModelException::class)]
#[Small]
final class ModelExceptionTest extends TestCase
{
    public function testGetMessagePreservesTheModelFailureCause(): void
    {
        $cause = new RuntimeException('source failure');
        $exception = new \Deriver\Model\ModelException('model failure', previous: $cause);
        self::assertSame('model failure', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
