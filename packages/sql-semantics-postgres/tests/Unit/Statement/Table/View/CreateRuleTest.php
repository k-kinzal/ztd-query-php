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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE RULE r AS ON UPDATE TO t WHERE old.a <> new.a DO ALSO SELECT new.b, old.a', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE RULE r AS ON DELETE TO t DO NOTHING', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE OR REPLACE RULE r AS ON INSERT TO s.t WHERE new.a > 0 DO INSTEAD (DELETE FROM u WHERE a = new.a; SELECT 1)', []);
        self::assertSame('CREATE OR REPLACE RULE r AS ON INSERT TO s.t WHERE new.a > 0 DO INSTEAD (DELETE FROM u WHERE a = new.a; SELECT 1)', $statement->toString());
    }

    public function testRefusesSeveralActionsWithoutParentheses(): void
    {
        $this->expectExceptionMessage('Several actions are written between parentheses.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule(new \SqlSemantics\Statement\Identifier\Name('r'), \SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::Insert, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1')->statement, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 2')->statement]);
    }
}
