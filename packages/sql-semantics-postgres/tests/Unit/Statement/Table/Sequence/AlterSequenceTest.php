<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence::class)]
#[Medium]
final class AlterSequenceTest extends TestCase
{
    public function testDeriveStatementReportsSequenceName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER SEQUENCE s SEQUENCE NAME x', []);
        self::assertSame([
          0 => 'Relation s does not exist.',
          1 => 'invalid sequence option SEQUENCE NAME',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheSequence(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER SEQUENCE s CYCLE');
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence::class, $n1);
        self::assertSame(0, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER SEQUENCE IF EXISTS x.s INCREMENT BY -1 RESTART', []);
        self::assertSame('ALTER SEQUENCE IF EXISTS x.s INCREMENT - 1 RESTART', $statement->toString());
    }
}
