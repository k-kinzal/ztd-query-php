<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoTableStatement::class)]
#[Medium]
final class SelectIntoTableStatementTest extends TestCase
{
    /**
     * @param list<string> $name
     */
    #[TestWith(['SELECT a INTO copied FROM t', ['copied'], Persistence::Permanent, 'SELECT "a" AS "a" INTO TABLE "copied" FROM "public"."t"'])]
    #[TestWith(['SELECT a INTO TABLE s.copied FROM t', ['s', 'copied'], Persistence::Permanent, 'SELECT "a" AS "a" INTO TABLE "s"."copied" FROM "public"."t"'])]
    #[TestWith(['SELECT a INTO LOCAL TEMP copied FROM t', ['copied'], Persistence::Temporary, 'SELECT "a" AS "a" INTO TEMPORARY TABLE "copied" FROM "public"."t"'])]
    #[TestWith(['SELECT a INTO temp FROM t', ['temp'], Persistence::Permanent, 'SELECT "a" AS "a" INTO TABLE "temp" FROM "public"."t"'])]
    #[TestWith(['SELECT a INTO UNLOGGED TABLE copied FROM t UNION SELECT 1', ['copied'], Persistence::Unlogged, 'SELECT "a" AS "a" INTO UNLOGGED TABLE "copied" FROM "public"."t" UNION SELECT 1'])]
    public function testBindsTheCreatedTable(string $sql, array $name, Persistence $persistence, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        self::assertSame([$name, $persistence], [$statement->table->parts, $statement->persistence]);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testWithTableReplacesTheNameAndPersistence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 INTO n');
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        $changed = $statement->withTable(new QualifiedName(['m']), Persistence::Unlogged);
        self::assertSame('SELECT 1 INTO UNLOGGED TABLE "m"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame(Persistence::Permanent, $statement->persistence);
    }

    public function testWithQueryReplacesTheFillingQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 INTO n');
        $query = $binder->bind('SELECT 2');
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame('SELECT 2 INTO TABLE "n"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withQuery($query)));
        self::assertSame('SELECT 1 INTO TABLE "n"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 INTO n');
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('other', $statement->source, Dialect::PostgreSql));
        self::assertSame([$statement->query, $statement->table], [$copy->query, $copy->table]);
    }

    public function testRejectsATemporaryTableInANamedSchema(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new SelectIntoTableStatement(new Origin('s', $query->source, Dialect::PostgreSql), $query, new QualifiedName(['public', 'n']), Persistence::Temporary);
    }

    public function testRejectsAMySqlQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new SelectIntoTableStatement(new Origin('s', $query->source, Dialect::MySql), $query, new QualifiedName(['n']));
    }
}
