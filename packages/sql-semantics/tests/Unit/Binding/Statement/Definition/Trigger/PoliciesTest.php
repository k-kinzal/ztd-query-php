<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Trigger\Policies;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyMode;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\AlterPolicyStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Policies::class)]
#[Medium]
final class PoliciesTest extends TestCase
{
    public function testBindReadsEveryClauseOfADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE app.docs(owner TEXT)'));
        $statement = $binder->bind('CREATE POLICY own ON app.docs AS RESTRICTIVE FOR UPDATE TO PUBLIC, CURRENT_USER USING (owner = CURRENT_USER) WITH CHECK (docs.owner IS NOT NULL)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame(['app', 'docs'], $statement->table->name->parts);
        self::assertSame(PolicyCommand::Update, $statement->command);
        self::assertSame([PublicRole::Public, SessionRole::CurrentUser], $statement->roles);
        self::assertSame('CREATE POLICY "own" ON "app"."docs" AS RESTRICTIVE FOR UPDATE TO PUBLIC, CURRENT_USER USING(("owner" = CURRENT_USER)) WITH CHECK(("docs"."owner" IS NOT NULL))', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindReadsAnAlterationWithOnlyItsChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER POLICY p ON t WITH CHECK (a > 0)');
        self::assertInstanceOf(AlterPolicyStatement::class, $statement);
        self::assertNull($statement->roles);
        self::assertNull($statement->using);
        self::assertNotNull($statement->check);
    }

    #[TestWith(['CREATE POLICY p ON t FOR INSERT USING (a > 0)'])]
    #[TestWith(['CREATE POLICY p ON t FOR SELECT WITH CHECK (a > 0)'])]
    #[TestWith(['CREATE POLICY p ON t FOR DELETE WITH CHECK (a > 0)'])]
    public function testBindDiagnosesAClauseTheCommandIgnores(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RowSecurityPolicy->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    #[TestWith(['AS Permissive', PolicyMode::Permissive])]
    #[TestWith(['AS restrictive', PolicyMode::Restrictive])]
    #[TestWith(['', PolicyMode::Permissive])]
    public function testModeReadsTheCombination(string $clause, PolicyMode $mode): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t ' . $clause);
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertSame($mode, $statement->mode);
    }

    #[TestWith(['AS "PERMISSIVE"'])]
    #[TestWith(['AS lenient'])]
    public function testModeRejectsAnotherWord(string $clause): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RowSecurityPolicy->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t ' . $clause);
    }

    public function testExpressionResolvesColumnsOfThePolicyTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t USING (missing > 0)', strict: false);
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        self::assertNotSame([], $statement->diagnostics);
    }
}
