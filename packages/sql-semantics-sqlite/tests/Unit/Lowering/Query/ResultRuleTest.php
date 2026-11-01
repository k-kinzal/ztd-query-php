<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Query\ResultRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(ResultRule::class)]
#[Medium]
final class ResultRuleTest extends TestCase
{
    public function testColumnsLowersExpressionsStarsAndTableStarsInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, *, t.*, b + 1 AS c FROM t');

        self::assertInstanceOf(Select::class, $operation);
        self::assertSame([ResultColumn::class, Star::class, TableStar::class, ResultColumn::class], array_map(static fn (object $column): string => $column::class, $operation->columns));
        self::assertInstanceOf(TableStar::class, $operation->columns[2]);
        self::assertSame('t', $operation->columns[2]->table->value);
        self::assertInstanceOf(ResultColumn::class, $operation->columns[3]);
        self::assertSame('c', $operation->columns[3]->alias?->value);
        self::assertSame('SELECT a, *, t.*, b + 1 AS c FROM t', $operation->toString());
    }

    public function testMarkerCarriesNothingIntoTheResultColumns(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1, t.*, * FROM t');

        self::assertInstanceOf(Select::class, $operation);
        self::assertCount(3, $operation->columns);
        self::assertInstanceOf(ResultColumn::class, $operation->columns[0]);
        self::assertInstanceOf(IntegerLiteral::class, $operation->columns[0]->expression);
        self::assertNull($operation->columns[0]->alias);
        self::assertSame('SELECT 1, t.*, * FROM t', $operation->toString());
    }

    public function testAliasReadsTheAliasAfterAsABareWordAStringOrNone(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a AS b, a c, a 'd', a \"e\", a FROM t");

        self::assertInstanceOf(Select::class, $operation);
        self::assertSame(['b', 'c', 'd', 'e', null], array_map(static function (object $column): ?string {
            self::assertInstanceOf(ResultColumn::class, $column);

            return $column->alias?->value;
        }, $operation->columns));
        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertSame(['b', 'c', 'd', 'e', 'a'], array_map(static fn (Field $field): ?string => $field->name?->value, iterator_to_array($fields)));
        self::assertSame('SELECT a AS b, a c, a d, a e, a FROM t', $operation->toString());
    }

    public function testColumnKeepsTheLayoutOfAnUnaliasedExpressionThatIsNotCanonical(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a+1, a + 1, a+1 AS x FROM t');
        self::assertInstanceOf(Select::class, $operation);
        $columns = $operation->columns;

        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertSame('a+1', $columns[0]->layout?->text());
        self::assertInstanceOf(ResultColumn::class, $columns[1]);
        self::assertNull($columns[1]->layout);
        self::assertInstanceOf(ResultColumn::class, $columns[2]);
        self::assertNull($columns[2]->layout);
        self::assertSame('SELECT a+1, a + 1, a + 1 AS x FROM t', $operation->toString());
    }

    public function testKeywordTellsWhetherAsIntroducesTheAlias(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a AS b, a c, a 'd', a FROM t AS x, t y, t");
        self::assertInstanceOf(Select::class, $operation);

        self::assertSame([true, false, false, true], array_map(static fn (ResultColumn|Star|TableStar $column): bool => $column instanceof ResultColumn && $column->as, $operation->columns));
        self::assertSame('SELECT a AS b, a c, a d, a FROM t AS x, t y, t', $operation->toString());
    }

    public function testQuantifierLowersDistinctAllOrNone(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $distinct = $semantics->analyze('SELECT DISTINCT a FROM t');
        $all = $semantics->analyze('SELECT ALL a FROM t');
        $plain = $semantics->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $distinct);
        self::assertInstanceOf(Select::class, $all);
        self::assertInstanceOf(Select::class, $plain);
        self::assertSame(SetQuantifier::Distinct, $distinct->quantifier);
        self::assertSame(SetQuantifier::All, $all->quantifier);
        self::assertNull($plain->quantifier);
        self::assertSame('SELECT DISTINCT a FROM t', $distinct->toString());
        self::assertSame('SELECT ALL a FROM t', $all->toString());
    }

    public function testValuesLowersEveryRowInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('VALUES (1, 2), (3, 4), (5, 6)');

        self::assertInstanceOf(ValuesClause::class, $operation);
        self::assertSame([['1', '2'], ['3', '4'], ['5', '6']], array_map(static fn (ValueRow $row): array => array_map(static function (object $value): string {
            self::assertInstanceOf(IntegerLiteral::class, $value);

            return $value->digits;
        }, $row->values), $operation->rows));
        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertSame(['column1', 'column2'], array_map(static fn (Field $field): ?string => $field->name?->value, iterator_to_array($fields)));
        self::assertSame('VALUES (1, 2), (3, 4), (5, 6)', $operation->toString());
    }

    public function testValuesLowersASingleRow(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("VALUES ('one')");

        self::assertInstanceOf(ValuesClause::class, $operation);
        self::assertCount(1, $operation->rows);
        self::assertCount(1, $operation->rows[0]->values);
        self::assertSame("VALUES ('one')", $operation->toString());
    }
}
