<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyMode;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreatePolicyStatement::class)]
#[Medium]
final class CreatePolicyStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t FOR UPDATE TO bob USING (a > 0) WITH CHECK (a < 9)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame('CREATE POLICY "q" ON "public"."t" AS PERMISSIVE FOR ALL TO PUBLIC', $statement->withName('q')->toString());
        self::assertSame('p', $statement->name);
    }

    public function testWithRolesRequiresARole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertStringEndsWith('TO "bob", SESSION_USER', $statement->withRoles([new NamedRole('bob'), SessionRole::SessionUser])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withRoles([]);
    }

    public function testWithModeReplacesTheCombination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame(PolicyMode::Restrictive, $statement->withMode(PolicyMode::Restrictive)->mode);
    }

    public function testWithCommandRejectsACommandTheExpressionsDoNotFit(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t USING (a > 0)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame(PolicyCommand::Select, $statement->withCommand(PolicyCommand::Select)->command);
        $this->expectException(InvalidStructure::class);
        $statement->withCommand(PolicyCommand::Insert);
    }

    public function testWithUsingRemovesTheVisibilityExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t USING (a > 0)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertNull($statement->withUsing(null)->using);
        self::assertNotNull($statement->using);
    }

    public function testWithCheckRemovesTheWrittenRowExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t WITH CHECK (a > 0)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertNull($statement->withCheck(null)->check);
        self::assertNotNull($statement->check);
    }
}
