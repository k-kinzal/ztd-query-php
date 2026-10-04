<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ItemNaming::class)]
#[Small]
final class ItemNamingTest extends TestCase
{
    public function testNameAnswersTheAlias(): void
    {
        self::assertSame('x', (new ItemNaming())->name(new SelectExpression(new NumberLiteral('1'), new Name('x')))?->value);
    }

    public function testNameAnswersTheColumnNameAsWrittenAlsoInParentheses(): void
    {
        self::assertSame('A', (new ItemNaming())->name(new SelectExpression(new ColumnUse(new Name('A'), new QualifiedName(new Name('t')))))?->value);
        self::assertSame('a', (new ItemNaming())->name(new SelectExpression(new Grouped(new ColumnUse(new Name('a')))))?->value);
    }

    public function testNameAnswersNullForAnotherExpression(): void
    {
        self::assertNull((new ItemNaming())->name(new SelectExpression(new NumberLiteral('1'))));
    }
}
