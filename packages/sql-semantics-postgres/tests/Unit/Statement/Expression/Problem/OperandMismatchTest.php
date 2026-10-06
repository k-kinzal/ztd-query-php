<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;

#[CoversClass(OperandMismatch::class)]
#[Small]
final class OperandMismatchTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Argument of AND must be type boolean, not type integer.', (new OperandMismatch('AND', 'boolean', 'integer'))->message());
    }
}
