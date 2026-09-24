<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Retrieval\SelectIntoBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoDumpfileStatement;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoOutfileStatement;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoTableStatement;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoVariablesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoBinder::class)]
#[Medium]
final class SelectIntoBinderTest extends TestCase
{
    public function testBindLeavesAQueryWithoutIntoUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
    }

    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testBindAcceptsIntoAfterTheLastQueryBlock(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('SELECT 1 UNION SELECT a FROM t INTO @x');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        self::assertSame('SELECT 1 UNION SELECT `a` AS `a` FROM `t` INTO @`x`', $statement->toString());
    }

    #[TestWith(['SELECT a INTO @x FROM t UNION SELECT 2', InputViolation::SelectInto])]
    #[TestWith(['SELECT (SELECT a INTO @x FROM t)', InputViolation::SelectInto])]
    #[TestWith(['SELECT a INTO @x, @y FROM t', InputViolation::SelectInto])]
    #[TestWith(['SELECT a INTO local_name FROM t', InputViolation::ProgramReference])]
    public function testBindRejectsAnImpossibleMySqlInto(string $sql, InputViolation $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    #[TestWith(['SELECT 1 UNION SELECT a INTO n FROM t'])]
    #[TestWith(['WITH c AS (SELECT a INTO n FROM t) SELECT * FROM c'])]
    public function testBindRejectsAnImpossiblePostgreSqlInto(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SelectInto->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind($sql);
    }

    public function testTableRejectsATemporaryTableInANamedSchema(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TemporaryTableSchema->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 INTO TEMP public.n');
    }

    public function testTableReadsThePersistenceKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 INTO GLOBAL TEMPORARY TABLE n');
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        self::assertSame([['n'], Persistence::Temporary], [$statement->table->parts, $statement->persistence]);
    }

    public function testDestinationDistinguishesFilesFromVariables(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertInstanceOf(SelectIntoDumpfileStatement::class, $binder->bind("SELECT 1 INTO DUMPFILE 'f'"));
        self::assertInstanceOf(SelectIntoOutfileStatement::class, $binder->bind("SELECT 1 INTO OUTFILE 'f'"));
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $binder->bind('SELECT 1 INTO @f'));
    }
}
