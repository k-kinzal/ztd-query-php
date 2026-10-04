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

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([ResultColumn::class, Star::class, TableStar::class, ResultColumn::class], array_map(static fn (object $column): string => $column::class, $operation->statement->columns));
        self::assertInstanceOf(TableStar::class, $operation->statement->columns[2]);
        self::assertSame('t', $operation->statement->columns[2]->table->value);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[3]);
        self::assertSame('c', $operation->statement->columns[3]->alias?->value);
        self::assertSame('SELECT a, *, t.*, b + 1 AS c FROM t', $operation->toString());
    }

    public function testAliasReadsTheAliasAfterAsABareWordAStringOrNone(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a AS b, a c, a 'd', a \"e\", a FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['b', 'c', 'd', 'e', null], array_map(static function (object $column): ?string {
            self::assertInstanceOf(ResultColumn::class, $column);

            return $column->alias?->value;
        }, $operation->statement->columns));
        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertSame(['b', 'c', 'd', 'e', 'a'], array_map(static fn (Field $field): ?string => $field->name?->value, iterator_to_array($fields)));
        self::assertSame('SELECT a AS b, a AS c, a AS d, a AS e, a FROM t', $operation->toString());
    }

    public function testQuantifierLowersDistinctAllOrNone(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $distinct = $semantics->analyze('SELECT DISTINCT a FROM t');
        $all = $semantics->analyze('SELECT ALL a FROM t');
        $plain = $semantics->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $distinct->statement);
        self::assertInstanceOf(Select::class, $all->statement);
        self::assertInstanceOf(Select::class, $plain->statement);
        self::assertSame(SetQuantifier::Distinct, $distinct->statement->quantifier);
        self::assertSame(SetQuantifier::All, $all->statement->quantifier);
        self::assertNull($plain->statement->quantifier);
        self::assertSame('SELECT DISTINCT a FROM t', $distinct->toString());
        self::assertSame('SELECT ALL a FROM t', $all->toString());
    }

    public function testValuesLowersEveryRowInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('VALUES (1, 2), (3, 4), (5, 6)');

        self::assertInstanceOf(ValuesClause::class, $operation->statement);
        self::assertSame([['1', '2'], ['3', '4'], ['5', '6']], array_map(static fn (ValueRow $row): array => array_map(static function (object $value): string {
            self::assertInstanceOf(IntegerLiteral::class, $value);

            return $value->digits;
        }, $row->values), $operation->statement->rows));
        $fields = $operation->fields();
        self::assertNotNull($fields);
        self::assertSame(['column1', 'column2'], array_map(static fn (Field $field): ?string => $field->name?->value, iterator_to_array($fields)));
        self::assertSame('VALUES (1, 2), (3, 4), (5, 6)', $operation->toString());
    }

    public function testValuesLowersASingleRow(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("VALUES ('one')");

        self::assertInstanceOf(ValuesClause::class, $operation->statement);
        self::assertCount(1, $operation->statement->rows);
        self::assertCount(1, $operation->statement->rows[0]->values);
        self::assertSame("VALUES ('one')", $operation->toString());
    }
}
