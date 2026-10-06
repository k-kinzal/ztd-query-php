<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\NameRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NameRule::class)]
#[Medium]
final class NameRuleTest extends TestCase
{
    public function testNameDecodesAnIdentifierAtANamePosition(): void
    {
        $select = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM "my table" AS [the alias]')->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(TableInput::class, $select->from);
        self::assertSame('my table', $select->from->name->name->value);
        self::assertSame('the alias', $select->from->alias?->value);
    }

    public function testNameAcceptsAStringAtANamePosition(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE 'tbl' (a)");

        self::assertInstanceOf(CreateTable::class, $operation->statement);
        self::assertSame('tbl', $operation->statement->name->name->value);
        self::assertSame('CREATE TABLE tbl (a)', $operation->toString());
    }

    public function testTokenDecodesAnIdentifierTokenAndKeepsItsCase(): void
    {
        $select = (new Semantics(Dialect::Sqlite))->analyze('SELECT [a b], `C``d`, MixedCase FROM t')->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame(['a b', 'C`d', 'MixedCase'], array_map(static function (object $column): string {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(ColumnUse::class, $column->expression);

            return $column->expression->name->value;
        }, $select->columns));
    }

    public function testScopedReadsTheSchemaWhenASecondNameFollows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('SELECT * FROM t')->statement;
        $scoped = $semantics->analyze('SELECT * FROM main.t')->statement;

        self::assertInstanceOf(Select::class, $plain);
        self::assertInstanceOf(Select::class, $scoped);
        self::assertInstanceOf(TableInput::class, $plain->from);
        self::assertInstanceOf(TableInput::class, $scoped->from);
        self::assertNull($plain->from->name->schema);
        self::assertSame('t', $plain->from->name->name->value);
        self::assertSame('main', $scoped->from->name->schema?->value);
        self::assertSame('t', $scoped->from->name->name->value);
    }

    public function testQualifiedLowersAFullnameWithAndWithoutASchema(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $qualified = $semantics->analyze('CREATE TRIGGER tr INSERT ON main.t BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $plain);
        self::assertInstanceOf(CreateTrigger::class, $qualified);
        self::assertNull($plain->table->name->schema);
        self::assertSame('main', $qualified->table->name->schema?->value);
        self::assertSame('t', $qualified->table->name->name->value);
    }

    public function testPairPutsTheFirstNameAsTheSchemaOfTheSecond(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('DELETE FROM temp.log');

        self::assertInstanceOf(Delete::class, $operation->statement);
        self::assertSame('temp', $operation->statement->target->name->schema?->value);
        self::assertSame('log', $operation->statement->target->name->name->value);
        self::assertSame('DELETE FROM `temp`.log', $operation->toString());
    }

    public function testListLowersTheNamesOfAnIdlistInWrittenOrder(): void
    {
        $insert = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO t (a, "b c", [d]) VALUES (1, 2, 3)')->statement;

        self::assertInstanceOf(InsertRows::class, $insert);
        self::assertSame(['a', 'b c', 'd'], array_map(static fn (Name $name): string => $name->value, $insert->into->columns));
    }

    public function testOptionalListGivesNoColumnsWithoutParentheses(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('INSERT INTO t VALUES (1)')->statement;
        $listed = $semantics->analyze('INSERT INTO t (a) VALUES (1)')->statement;

        self::assertInstanceOf(InsertRows::class, $bare);
        self::assertInstanceOf(InsertRows::class, $listed);
        self::assertSame([], $bare->into->columns);
        self::assertCount(1, $listed->into->columns);
    }

    public function testCollationLowersTheCollationNameOrNullWhenNoneIsWritten(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c (a COLLATE nocase, b, d COLLATE "no case") AS (SELECT 1, 2, 3) SELECT * FROM c')->statement;

        self::assertInstanceOf(WithQuery::class, $query);
        $columns = $query->with->tables[0]->columns;
        self::assertSame('nocase', $columns[0]->collation?->value);
        self::assertNull($columns[1]->collation);
        self::assertSame('no case', $columns[2]->collation?->value);
    }

    public function testCollationOfAnExpressionIsDecodedLikeAnyName(): void
    {
        $select = (new Semantics(Dialect::Sqlite))->analyze("SELECT a COLLATE 'no case' FROM t")->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ResultColumn::class, $select->columns[0]);
        self::assertInstanceOf(Collate::class, $select->columns[0]->expression);
        self::assertSame('no case', $select->columns[0]->expression->collation->value);
    }
}
