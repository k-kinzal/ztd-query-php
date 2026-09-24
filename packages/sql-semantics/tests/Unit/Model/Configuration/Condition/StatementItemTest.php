<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\StatementItem;
use SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatementItem::class)]
#[Medium]
final class StatementItemTest extends TestCase
{
    public function testBindsEachStatementItemKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @a = NUMBER, @b = row_count');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        self::assertSame([StatementItem::Number, StatementItem::RowCount], array_column($statement->items, 'item'));
    }
}
