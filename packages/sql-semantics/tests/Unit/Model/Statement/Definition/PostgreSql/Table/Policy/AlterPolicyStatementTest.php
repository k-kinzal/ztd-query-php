<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\AlterPolicyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterPolicyStatement::class)]
#[Medium]
final class AlterPolicyStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t TO PUBLIC USING (a > 0) WITH CHECK (a < 9)');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Alter, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        self::assertSame('ALTER POLICY "q" ON "public"."t"', $statement->withName('q')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithRolesDistinguishesKeepingFromReplacing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        self::assertNull($statement->roles);
        self::assertSame('ALTER POLICY "p" ON "public"."t" TO PUBLIC', $statement->withRoles([PublicRole::Public])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withRoles([]);
    }

    public function testWithUsingKeepsTheCurrentExpressionWithNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t USING (a > 0)');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        self::assertSame('ALTER POLICY "p" ON "public"."t"', $statement->withUsing(null)->toString());
    }

    public function testWithCheckKeepsTheCurrentExpressionWithNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t WITH CHECK (a > 0)');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        self::assertNull($statement->withCheck(null)->check);
        self::assertNotNull($statement->check);
    }
}
