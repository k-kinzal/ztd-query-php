<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;

#[CoversClass(ConditionalColumn::class)]
#[Medium]
final class ConditionalColumnTest extends TestCase
{
    public function testRelationsAndMissingNameTheUndeclaredOccurrence(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t');

        $resolution = $query->field('a')->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame('a', $resolution->name->value);
        self::assertSame([], $resolution->candidates);
        self::assertSame([$query->inputRelation()], $resolution->relations);
        self::assertInstanceOf(UndeclaredRelation::class, $resolution->missing[0]);
        self::assertSame('the declaration of relation t', $resolution->missing[0]->describe());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testCandidatesKeepAKnownSlotWhileANearerOccurrenceIsUnknown(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TEMP TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM t, u', $semantics->context([$table], false));
        $join = $query->inputRelation();

        $resolution = $query->field('a')->resolution;

        self::assertInstanceOf(JoinChain::class, $join);
        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertCount(1, $resolution->candidates);
        self::assertSame($join->first, $resolution->candidates[0]->relation);
        self::assertSame([$join->steps[0]->relation], $resolution->relations);
        self::assertCount(1, $resolution->missing);
    }

    public function testMissingIsNeverEmpty(): void
    {
        $this->expectExceptionMessage('A conditional column names its missing inputs.');

        new ConditionalColumn(new Name('a'), [], [], []);
    }
}
