<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ModelContractException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Deriver\Exception\ModelContractException
 */
#[CoversClass(ModelContractException::class)]
#[Small]
final class ModelContractExceptionTest extends TestCase
{
    public function testGetMessagePreservesTheModelFailureCause(): void
    {
        $cause = new RuntimeException('source failure');
        $exception = new ModelContractException('model failure', previous: $cause);
        self::assertSame('model failure', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
