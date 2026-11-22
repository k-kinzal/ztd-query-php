<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
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

    public function testChoiceListsDormantAndActiveOperandsInWrittenOrder(): void
    {
        $one = new IntegerLiteral('1');
        $column = new ColumnReference(0, 1);
        $value = new CaseExpression($column, [new CaseBranch($one, $column)], $one);

        self::assertSame([[$column, 2, 3], [$one, 2, 3], [$column, 2, 3], [$one, 2, 3]], (new ExpressionScope())->choice($value, 2, 3));
    }
}
