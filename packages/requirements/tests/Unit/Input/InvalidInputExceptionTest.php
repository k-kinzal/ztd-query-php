<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Input\InvalidInputException;
use RuntimeException;

#[CoversClass(InvalidInputException::class)]
#[Small]
final class InvalidInputExceptionTest extends TestCase
{
    /**
     * @throws InvalidInputException
     */
    public function testIsCaughtAsRuntimeExceptionWithItsMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('definition.yaml: unknown field.');
        throw new InvalidInputException('definition.yaml: unknown field.');
    }

    public function testKeepsThePreviousException(): void
    {
        $previous = new RuntimeException('cause');
        $error = new InvalidInputException('wrapped', 0, $previous);
        self::assertSame($previous, $error->getPrevious());
        self::assertSame('wrapped', $error->getMessage());
    }
}
