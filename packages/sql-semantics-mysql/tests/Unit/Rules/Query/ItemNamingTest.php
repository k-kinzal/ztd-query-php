<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ItemNaming::class)]
#[Small]
final class ItemNamingTest extends TestCase
{
    public function testNameAnswersTheAliasWhenOneIsWritten(): void
    {
        $alias = new Name('total');

        self::assertSame($alias, (new ItemNaming())->name(new SelectExpression(new ColumnUse(new Name('amount')), $alias)));
        self::assertSame($alias, (new ItemNaming())->name(new SelectExpression(new NumberLiteral('1'), $alias)));
    }

    public function testNameAnswersTheColumnNameOfAnUnaliasedColumnReference(): void
    {
        $column = new Name('Amount');

        self::assertSame($column, (new ItemNaming())->name(new SelectExpression(new ColumnUse($column))));
    }

    public function testNameIsNullForAnUnaliasedExpressionNamedByItsSpelling(): void
    {
        self::assertNull((new ItemNaming())->name(new SelectExpression(new NumberLiteral('1'))));
    }
}
