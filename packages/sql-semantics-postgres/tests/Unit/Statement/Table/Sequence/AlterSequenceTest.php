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
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER SEQUENCE s SEQUENCE NAME x', []);
        self::assertSame([
          0 => 'Relation s does not exist.',
          1 => 'invalid sequence option SEQUENCE NAME',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveRelationResolvesTheSequence(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER SEQUENCE s CYCLE');
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence::class, $n1);
        self::assertSame(0, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER SEQUENCE IF EXISTS x.s INCREMENT BY -1 RESTART', []);
        self::assertSame('ALTER SEQUENCE IF EXISTS x.s INCREMENT - 1 RESTART', $statement->toString());
    }

    public function testDeriveStatementReportsATableInPostgreSql16(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-16.6');
        $context = [$semantics->diagnose('CREATE TABLE t (a int)')];
        self::assertSame(['"t" is not a sequence'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER SEQUENCE t RESTART', $context)->facts->diagnostics));
    }

    public function testDeriveStatementReportsATableInPostgreSql17(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $context = [$semantics->diagnose('CREATE TABLE t (a int)'), $semantics->diagnose('CREATE SEQUENCE s')];
        self::assertSame(['cannot open relation "t"'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER SEQUENCE IF EXISTS t RESTART', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER SEQUENCE s RESTART', $context)->facts->diagnostics));
    }
}
