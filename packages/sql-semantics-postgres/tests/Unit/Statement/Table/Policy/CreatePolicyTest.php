<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\CreatePolicy::class)]
#[Medium]
final class CreatePolicyTest extends TestCase
{
    public function testDeriveStatementDerivesTheConditionsAndTheMode(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE POLICY p ON t AS lenient USING (zz)', $context);
        self::assertSame([
          0 => 'unrecognized row security option "lenient"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE POLICY p ON t', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\CreatePolicy::class, $n1);
        self::assertSame(2, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE POLICY p ON t AS PERMISSIVE FOR ALL TO PUBLIC USING (true) WITH CHECK (a > 0)', []);
        self::assertSame('CREATE POLICY p ON t AS permissive FOR ALL TO public USING (TRUE) WITH CHECK (a > 0)', $statement->toString());
    }
}
