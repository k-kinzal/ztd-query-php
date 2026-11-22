<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\AlterPolicy::class)]
#[Medium]
final class AlterPolicyTest extends TestCase
{
    public function testDeriveStatementDerivesTheConditionsAgainstTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER POLICY p ON t USING (zz > 0) WITH CHECK (a)', $context);
        self::assertSame([
          0 => 'Column zz does not exist.',
          1 => 'argument of POLICY must be type boolean',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER POLICY p ON t TO bob', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\AlterPolicy::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER POLICY p ON s.t TO CURRENT_USER, bob USING (a > 0) WITH CHECK (b > 0)', []);
        self::assertSame('ALTER POLICY p ON s.t TO CURRENT_USER, bob USING (a > 0) WITH CHECK (b > 0)', $statement->toString());
    }
}
