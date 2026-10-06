<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\CommonTable as CommonTableReference;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(WithQuery::class)]
#[Medium]
final class WithQueryTest extends TestCase
{
    public function testDeriveStatementRecordsTheOutputOfTheBody(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1 AS x) SELECT x FROM c', []);

        self::assertInstanceOf(WithQuery::class, $query->statement);
        self::assertInstanceOf(Select::class, $query->statement->body);
        self::assertSame('x', $query->field(0)->name?->value);
        self::assertInstanceOf(Known::class, $query->field('x')->type);
        self::assertSame(Storage::Integer, $query->field('x')->type->descriptor);
        $input = $query->statement->body->from;
        self::assertNotNull($input);
        $reference = $query->facts->relation($input)->table;
        self::assertInstanceOf(CommonTableReference::class, $reference);
        self::assertSame($query->statement->with->tables[0], $reference->definition);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveQueryMakesTheCommonTablesVisibleInNestedQueries(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1 AS x) SELECT (SELECT x FROM c) AS y FROM (SELECT x FROM c)', []);

        self::assertInstanceOf(Known::class, $query->field('y')->type);
        self::assertSame(Nullability::Nullable, $query->field('y')->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveQueryTypesARecursiveColumnAsAnyStorageClassInTheRecursiveArm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH RECURSIVE c(x) AS (SELECT 1 UNION ALL SELECT x + 1 FROM c WHERE x < 5) SELECT x FROM c', []);

        self::assertInstanceOf(WithQuery::class, $query->statement);
        $compound = $query->statement->with->tables[0]->query;
        self::assertInstanceOf(Compound::class, $compound);
        $recursive = $compound->steps[0]->query;
        self::assertInstanceOf(Select::class, $recursive);
        self::assertInstanceOf(ResultColumn::class, $recursive->columns[0]);
        self::assertInstanceOf(Binary::class, $recursive->columns[0]->expression);
        $reference = $query->facts->scalar($recursive->columns[0]->expression->left);
        self::assertInstanceOf(Choice::class, $reference->type);
        self::assertSame(Storage::cases(), $reference->type->alternatives);
        self::assertSame(Nullability::Nullable, $reference->nullability);
        self::assertInstanceOf(Choice::class, $query->field('x')->type);
        self::assertSame([Storage::Integer, Storage::Real], $query->field('x')->type->alternatives);
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1) SELECT RAISE(IGNORE)');

        self::assertSame(MisuseRule::RaiseOutsideTrigger->value, $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheClauseAndTheBody(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze('with recursive c(n) as (select 1 union all select n + 1 from c) select n from c limit 3');
        $values = $semantics->analyze('with c as (select 1) values (2)');

        self::assertSame('WITH RECURSIVE c (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c) SELECT n FROM c LIMIT 3', $compound->toString());
        self::assertInstanceOf(WithQuery::class, $values->statement);
        self::assertInstanceOf(ValuesClause::class, $values->statement->body);
        self::assertSame('WITH c AS (SELECT 1) VALUES (2)', $values->toString());
    }

    public function testRenderWritesANewlyBuiltQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $with = new WithClause([new CommonTable(new Name('c'), new Select([new ResultColumn(new IntegerLiteral('1'), new Name('x'))]))]);
        $operation = new Operation($semantics->context([]), new WithQuery($with, new Select([new Star()], new TableInput(new QualifiedName(new Name('c'))))));

        self::assertSame('WITH c AS (SELECT 1 AS x) SELECT * FROM c', $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
    }
}
