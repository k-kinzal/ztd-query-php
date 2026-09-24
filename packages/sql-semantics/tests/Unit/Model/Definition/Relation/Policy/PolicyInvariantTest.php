<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyInvariant;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PolicyInvariant::class)]
#[Medium]
final class PolicyInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'p'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testIdentityRejectsAnotherLanguageOrAnEmptyName(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PolicyInvariant::identity($origin, $name, null, null);
    }

    public function testIdentityRejectsAnExpressionOfAnotherLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $trigger = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER tr AFTER INSERT ON t WHEN 1 BEGIN SELECT 1; END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $trigger);
        $this->expectException(InvalidStructure::class);
        PolicyInvariant::identity($origin, 'p', $trigger->when, null);
    }

    public function testRolesRequiresAtLeastOneRole(): void
    {
        self::assertSame([PublicRole::Public], PolicyInvariant::roles([PublicRole::Public]));
        $this->expectException(InvalidStructure::class);
        PolicyInvariant::roles([]);
    }

    #[TestWith([PolicyCommand::Insert, true, false])]
    #[TestWith([PolicyCommand::Select, false, true])]
    #[TestWith([PolicyCommand::Delete, false, true])]
    public function testExpressionsRejectAClauseTheCommandIgnores(PolicyCommand $command, bool $using, bool $check): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE POLICY p ON t USING (a > 0) WITH CHECK (a > 0)');
        self::assertInstanceOf(CreatePolicyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        PolicyInvariant::expressions($command, $using ? $statement->using : null, $check ? $statement->check : null);
    }
}
