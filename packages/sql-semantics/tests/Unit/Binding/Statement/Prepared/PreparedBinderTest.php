<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Prepared\PreparedBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PreparedBinder::class)]
#[Medium]
final class PreparedBinderTest extends TestCase
{
    public function testQueryBindsPositionalParametersWithTheirDeclaredTypes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('PREPARE p(int, text) AS SELECT $1, $2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement::class, $statement);
        self::assertSame('p', $statement->name);
        self::assertSame(['integer', 'text'], array_map(static fn ($type): string => $type->name, $statement->parameterTypes));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->statement);
        self::assertSame(['integer', 'text'], array_map(static fn ($output): string => $output->expression->type->name, $statement->statement->outputs));
        self::assertSame('PREPARE "p"(integer, text) AS SELECT $1, $2', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testQueryAcceptsMutationsAsPreparableStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('PREPARE p AS INSERT INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement->statement);
        self::assertSame([], $statement->parameterTypes);
    }

    public function testTextRetainsALiteralOrVariableSourceWithoutParsingIt(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $literal = $binder->bind("PREPARE s FROM 'SELECT 1'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareTextStatement::class, $literal);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal->sql);
        self::assertSame("'SELECT 1'", $literal->sql->text);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $literal->sql->literalKind);
        $variable = $binder->bind('PREPARE s FROM @v', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareTextStatement::class, $variable);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $variable->sql);
        self::assertSame('v', $variable->sql->name);
        self::assertSame('PREPARE `s` FROM @`v`', (new \SqlSemantics\SimpleSerializer())->serialize($variable));
    }

    public function testBindSeparatesExecutionAndDeallocationForms(): void
    {
        $postgres = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $execute = $postgres->bind('EXECUTE p(1, 2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\ExecuteQueryStatement::class, $execute);
        self::assertSame('p', $execute->name);
        self::assertCount(2, $execute->arguments);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\DeallocateAllStatement::class, $postgres->bind('DEALLOCATE ALL'));
        $deallocate = $postgres->bind('DEALLOCATE p');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\DeallocateStatement::class, $deallocate);
        self::assertSame('p', $deallocate->name);
        $mysql = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $using = $mysql->bind('EXECUTE s USING @a, @b', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\ExecuteUsingStatement::class, $using);
        self::assertSame(['a', 'b'], array_column($using->variables, 'name'));
        self::assertSame('EXECUTE `s` USING @`a`, @`b`', (new \SqlSemantics\SimpleSerializer())->serialize($using));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\DeallocateStatement::class, $mysql->bind('DROP PREPARE s'));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS VALUES (1)', 'PREPARE "p" AS VALUES (1)'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS TABLE t', 'PREPARE "p" AS TABLE "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS SELECT 1 UNION SELECT 2', 'PREPARE "p" AS SELECT 1 UNION SELECT 2'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS UPDATE t SET a = 1', 'PREPARE "p" AS UPDATE "public"."t" SET "a" = 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS DELETE FROM t', 'PREPARE "p" AS DELETE FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PREPARE p AS MERGE INTO t USING t AS s ON true WHEN MATCHED THEN DELETE', 'PREPARE "p" AS MERGE INTO "public"."t" USING "public"."t" AS "s" ON true WHEN MATCHED THEN DELETE'])]
    public function testQueryAcceptsEveryPreparableStatement(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
