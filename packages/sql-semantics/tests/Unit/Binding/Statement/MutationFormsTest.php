<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\MutationForms::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MutationFormsTest extends TestCase
{
    public function testUpdateDoesNotReadConflictKeywordsFromCtes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('WITH c AS (VALUES (?1 OR ?1)) UPDATE t SET id=?9');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Write\Policy\ConstraintResponse::Default, $statement->onViolation);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDeleteReadsMySqlModifiersAndPagination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('DELETE LOW_PRIORITY QUICK IGNORE FROM t WHERE a = 1 ORDER BY a LIMIT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $statement);
        self::assertTrue($statement->lowPriority);
        self::assertTrue($statement->quick);
        self::assertTrue($statement->ignore);
        self::assertCount(1, $statement->orderBy);
        self::assertNotNull($statement->limit);
        self::assertSame('DELETE LOW_PRIORITY QUICK IGNORE FROM `t` WHERE (`a` = 1) ORDER BY `a` ASC LIMIT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testDeleteSeparatesJoinedAndUsingForms(): void
    {
        $joined = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('DELETE LOW_PRIORITY t FROM t JOIN u ON t.a = u.a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement::class, $joined);
        self::assertTrue($joined->lowPriority);
        self::assertSame('t', $joined->targets[0]->declaration->name);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $joined->from);
        $using = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('DELETE FROM t USING u WHERE t.a = u.a RETURNING t.a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteUsingStatement::class, $using);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $using->using);
        self::assertSame('u', $using->using->declaration->name);
        self::assertCount(1, $using->outputs);
        self::assertSame('DELETE FROM "public"."t" USING "public"."u" WHERE ("t"."a" = "u"."a") RETURNING "t"."a" AS "a"', (new \SqlSemantics\SimpleSerializer())->serialize($using));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['UPDATE t JOIN u ON t.a = u.a SET t.a = 1 LIMIT 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['UPDATE t JOIN u ON t.a = u.a SET t.a = 1 ORDER BY t.a'])]
    public function testUpdateRejectsPaginationOnAJoinedMySqlUpdate(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::JoinedMutationPagination->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['update low_priority ignore t set a = 1', true, true, 'UPDATE LOW_PRIORITY IGNORE `t` SET `a` = 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['UPDATE t SET t.ignore = 1', false, false, 'UPDATE `t` SET `t`.`ignore` = 1'])]
    public function testUpdateReadsMySqlModifiersBeforeSet(string $sql, bool $lowPriority, bool $ignore, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, `ignore` INT)')))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        self::assertSame([$lowPriority, $ignore], [$statement->lowPriority, $statement->ignore]);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['update or ignore t set a = 1', \SqlSemantics\Model\Write\Policy\ConstraintResponse::Ignore, 'UPDATE OR IGNORE "main"."t" SET "a" = 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['update or replace t set a = 1', \SqlSemantics\Model\Write\Policy\ConstraintResponse::Replace, 'UPDATE OR REPLACE "main"."t" SET "a" = 1'])]
    public function testUpdateReadsTheSqliteConflictResolution(string $sql, \SqlSemantics\Model\Write\Policy\ConstraintResponse $response, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        self::assertSame($response, $statement->onViolation);
        self::assertFalse($statement->ignore);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testUpdateSeparatesJoinedAndFromForms(): void
    {
        $joined = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('UPDATE t JOIN t AS u ON t.a = u.a SET t.a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement::class, $joined);
        self::assertSame('UPDATE `t` INNER JOIN `t` AS `u` ON (`t`.`a` = `u`.`a`) SET `t`.`a` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($joined));
        $from = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('UPDATE t SET a = u.a FROM t AS u WHERE t.a = u.a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateFromStatement::class, $from);
        self::assertSame('UPDATE "public"."t" SET "a" = "u"."a" FROM "public"."t" AS "u" WHERE ("t"."a" = "u"."a")', (new \SqlSemantics\SimpleSerializer())->serialize($from));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['delete low_priority quick ignore from t', true, true, true, 'DELETE LOW_PRIORITY QUICK IGNORE FROM `t`'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DELETE FROM t WHERE quick = 1', false, false, false, 'DELETE FROM `t` WHERE (`quick` = 1)'])]
    public function testDeleteReadsMySqlModifiersBeforeFrom(string $sql, bool $lowPriority, bool $quick, bool $ignore, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, quick INT)')))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $statement);
        self::assertSame([$lowPriority, $quick, $ignore], [$statement->lowPriority, $statement->quick, $statement->ignore]);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testDeleteTreatsAMySqlTableListAsAJoinedDelete(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('DELETE t FROM t WHERE a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement::class, $statement);
        self::assertSame('DELETE `t` FROM `t` WHERE (`a` = 1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param list<string> $words
     * @param list<string> $expected
     */
    #[\PHPUnit\Framework\Attributes\TestWith([['UPDATE', 'LOW_PRIORITY', 'IGNORE', 'T', 'SET'], 'UPDATE', ['LOW_PRIORITY', 'IGNORE']])]
    #[\PHPUnit\Framework\Attributes\TestWith([['UPDATE', 'T', 'IGNORE', 'INDEX', '(', 'K', ')', 'SET'], 'UPDATE', []])]
    #[\PHPUnit\Framework\Attributes\TestWith([['DELETE', 'QUICK', 'T', 'FROM', 'T', 'IGNORE', 'INDEX'], 'DELETE', ['QUICK']])]
    public function testModifiersReadOnlyTheWordsRightAfterTheVerb(array $words, string $verb, array $expected): void
    {
        self::assertSame($expected, \SqlSemantics\Binding\Statement\MutationForms::modifiers($words, $verb));
    }

    public function testUpdateDoesNotReadAnIgnoreIndexHintAsTheIgnoreModifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k (a))'));
        $statement = $binder->bind('UPDATE t IGNORE INDEX FOR ORDER BY (k) JOIN t AS u ON t.a = u.a SET t.a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement::class, $statement);
        self::assertFalse($statement->ignore);
        self::assertSame('UPDATE `t` IGNORE INDEX FOR ORDER BY(`k`) INNER JOIN `t` AS `u` ON (`t`.`a` = `u`.`a`) SET `t`.`a` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testDeleteDoesNotReadAnIgnoreIndexHintAsTheIgnoreModifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k (a))'));
        $statement = $binder->bind('DELETE t FROM t IGNORE INDEX (k) JOIN t AS u ON t.a = u.a');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement::class, $statement);
        self::assertFalse($statement->ignore);
        self::assertSame('DELETE `t` FROM `t` IGNORE INDEX(`k`) INNER JOIN `t` AS `u` ON (`t`.`a` = `u`.`a`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
