<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\StatementException;

#[CoversClass(StatementException::class)]
#[Small]
final class StatementExceptionTest extends TestCase
{
    public function testIsARuntimeFailureWithItsMessage(): void
    {
        $error = new StatementException('The statement is read back as other SQL');
        self::assertSame('The statement is read back as other SQL', $error->getMessage());
        self::assertSame(0, $error->getCode());
    }
}
