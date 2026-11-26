<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule::class)]
#[Medium]
final class CreateRuleTest extends TestCase
{
    public function testDeriveStatementSeesOldAndNew(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE RULE r AS ON UPDATE TO t WHERE old.a <> new.a DO ALSO SELECT new.b, old.a', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE RULE r AS ON DELETE TO t DO NOTHING', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE OR REPLACE RULE r AS ON INSERT TO s.t WHERE new.a > 0 DO INSTEAD (DELETE FROM u WHERE a = new.a; SELECT 1)', []);
        self::assertSame('CREATE OR REPLACE RULE r AS ON INSERT TO s.t WHERE new.a > 0 DO INSTEAD (DELETE FROM u WHERE a = new.a; SELECT 1)', $statement->toString());
    }

    public function testRefusesSeveralActionsWithoutParentheses(): void
    {
        $this->expectExceptionMessage('Several actions are written between parentheses.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('r'), \SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::Insert, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), [(new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT 1')->statement, (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT 2')->statement]);
    }
}
