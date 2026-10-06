<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::class)]
#[Medium]
final class ConstraintKindTest extends TestCase
{
    public function testCasesNameTheConstraintTypes(): void
    {
        self::assertSame(10, count(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::cases()));
    }
}
