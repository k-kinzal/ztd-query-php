<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\Exception\UnanalyzedColumnException;

#[CoversClass(UnanalyzedColumnException::class)]
final class UnanalyzedColumnExceptionTest extends TestCase
{
    public function testDescribesTheColumn(): void
    {
        $exception = new UnanalyzedColumnException('amount');

        self::assertSame('Column could not be analyzed: amount', $exception->getMessage());
        self::assertSame('amount', $exception->columnName);
    }
}
