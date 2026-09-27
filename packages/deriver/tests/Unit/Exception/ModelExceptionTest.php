<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ModelException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Deriver\Exception\ModelException
 */
#[CoversClass(ModelException::class)]
#[Small]
final class ModelExceptionTest extends TestCase
{
    public function testGetMessagePreservesTheModelFailureCause(): void
    {
        $cause = new RuntimeException('source failure');
        $exception = new ModelException('model failure', previous: $cause);
        self::assertSame('model failure', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
