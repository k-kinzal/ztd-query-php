<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateConstraintTrigger::class)]
#[Medium]
final class CreateConstraintTriggerTest extends TestCase
{
    public function testDeriveStatementResolvesTheTablesAndTheCondition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE CONSTRAINT TRIGGER g AFTER UPDATE ON t FROM u NOT VALID FOR EACH ROW WHEN (new.zz > 0) EXECUTE FUNCTION f()', $context);
        self::assertSame([
          0 => 'Relation u does not exist.',
          1 => 'TRIGGER constraints cannot be marked NOT VALID',
          2 => 'Column new.zz does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateConstraintTrigger::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE OR REPLACE CONSTRAINT TRIGGER g AFTER INSERT OR UPDATE OF a ON t FROM s.u INITIALLY DEFERRED FOR EACH ROW WHEN (new.a > 0) EXECUTE PROCEDURE f(1)', []);
        self::assertSame('CREATE OR REPLACE CONSTRAINT TRIGGER g AFTER INSERT OR UPDATE OF a ON t FROM s.u INITIALLY DEFERRED FOR EACH ROW WHEN (new.a > 0) EXECUTE FUNCTION f(1)', $statement->toString());
    }
}
