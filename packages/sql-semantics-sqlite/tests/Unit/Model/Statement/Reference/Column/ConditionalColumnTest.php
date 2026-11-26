<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ConditionalColumn::class)]
#[Medium]
final class ConditionalColumnTest extends TestCase
{
    public function testRelationsAndMissingNameTheUndeclaredOccurrence(): void
    {
        $query = (new Inspection())->inspect('SELECT a FROM t');

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
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TEMP TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM t, u', $semantics->context([$table], false));
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
