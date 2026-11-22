<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;

#[CoversClass(ExpressionScope::class)]
#[Small]
final class ExpressionScopeTest extends TestCase
{
    public function testCheckAcceptsAColumnInTheVisiblePrefix(): void
    {
        $column = new ColumnReference(0, 1);

        (new ExpressionScope())->check([1, 1], [[$column, 0, 2]]);

        self::assertSame(1, $column->source);
    }
}
