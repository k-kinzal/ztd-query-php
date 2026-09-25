<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame('SELECT 1 UNION SELECT `a` AS `a` FROM `t` INTO @`x`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryIntoDestination')]
    public function testBindReadsEveryIntoDestination(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryIntoDestination(): iterable
    {
        return [
            'select a into temp x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into temp x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TEMPORARY TABLE "x" FROM "public"."t"'],
            'select a into local temporary x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into local temporary x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TEMPORARY TABLE "x" FROM "public"."t"'],
            'select a into global temp table x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into global temp table x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TEMPORARY TABLE "x" FROM "public"."t"'],
            'select a into unlogged x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into unlogged x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO UNLOGGED TABLE "x" FROM "public"."t"'],
            'select a into temporary pg_temp.x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into temporary pg_temp.x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TEMPORARY TABLE "pg_temp"."x" FROM "public"."t"'],
            'select a into temp pg_temp_3.x from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'select a into temp pg_temp_3.x from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TEMPORARY TABLE "pg_temp_3"."x" FROM "public"."t"'],
            'SELECT a INTO public.x FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT)'], 'SELECT a INTO public.x FROM t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoTableStatement => SELECT "a" AS "a" INTO TABLE "public"."x" FROM "public"."t"'],
            'select a into dumpfile \'/tmp/a\' from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'select a into dumpfile \'/tmp/a\' from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoDumpfileStatement => SELECT `a` AS `a` FROM `t` INTO DUMPFILE \'/tmp/a\''],
            'select a into outfile \'/tmp/a\' from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'select a into outfile \'/tmp/a\' from t', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoOutfileStatement => SELECT `a` AS `a` FROM `t` INTO OUTFILE \'/tmp/a\''],
            'select a from t into @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'select a from t into @x', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoVariablesStatement => SELECT `a` AS `a` FROM `t` INTO @`x`'],
            'SELECT a FROM t INTO @`x` (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'SELECT a FROM t INTO @`x`', 'SqlSemantics\\Model\\Statement\\Retrieval\\SelectIntoVariablesStatement => SELECT `a` AS `a` FROM `t` INTO @`x`'],
        ];
    }

    #[TestWith(['SELECT a INTO TEMP public.x FROM t'])]
    #[TestWith(['SELECT a INTO TEMP pg_temp_x.x FROM t'])]
    #[TestWith(['SELECT a INTO TEMP xpg_temp.x FROM t'])]
    #[TestWith(['SELECT a INTO TEMP pg_temp_3x.x FROM t'])]
    public function testTableRejectsATemporaryTableOutsideTheTemporarySchema(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
