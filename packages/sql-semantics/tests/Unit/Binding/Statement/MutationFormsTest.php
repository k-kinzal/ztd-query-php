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
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
        self::assertSame('DELETE LOW_PRIORITY QUICK IGNORE FROM `t` WHERE (`a` = 1) ORDER BY `a` ASC LIMIT 1', $statement->toString());
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
        self::assertSame('DELETE FROM "public"."t" USING "public"."u" WHERE ("t"."a" = "u"."a") RETURNING "t"."a" AS "a"', $using->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['UPDATE t JOIN u ON t.a = u.a SET t.a = 1 LIMIT 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['UPDATE t JOIN u ON t.a = u.a SET t.a = 1 ORDER BY t.a'])]
    public function testUpdateRejectsPaginationOnAJoinedMySqlUpdate(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::JoinedMutationPagination->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql);
    }
}
