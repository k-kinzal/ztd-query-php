<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;

#[CoversClass(ExplainFacts::class)]
#[Medium]
final class ExplainFactsTest extends TestCase
{
    public function testKnownAcceptsOnlyTheFiniteFormatDomain(): void
    {
        $facts = new ExplainFacts();
        self::assertTrue($facts->known(null));
        self::assertTrue($facts->known(new \SqlSemantics\Statement\Identifier\Name('jSoN')));
        self::assertTrue($facts->known(new \SqlSemantics\Statement\Identifier\Name('TREE')));
        self::assertFalse($facts->known(new \SqlSemantics\Statement\Identifier\Name('XML')));
    }

    public function testRefusalRejectsUnavailableInputBeforePublishingAStatement(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Unknown EXPLAIN format name');
        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('EXPLAIN FORMAT = TREE SELECT 1');
    }

    public function testRefusalAnswersTheBrokenRule(): void
    {
        $facts = new ExplainFacts();
        self::assertSame(UtilityRule::UnknownExplainFormat, $facts->refusal(GrammarRelease::MySql5744, 'TREE', false, false));
        self::assertNull($facts->refusal(GrammarRelease::MySql847, 'TREE', true, false));
        self::assertSame(UtilityRule::ExplainIntoFormat, $facts->refusal(GrammarRelease::MySql847, null, false, true));
    }

    public function testRowsAnswersOneColumnForADocument(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE SELECT 1')->fields() ?? []);
    }

    public function testLegacyTellsTheReleasesWithoutTree(): void
    {
        self::assertTrue((new ExplainFacts())->legacy(GrammarRelease::MySql5651));
        self::assertFalse((new ExplainFacts())->legacy(GrammarRelease::MySql8044));
    }

    public function testDeriveAnswersTheLayoutOfTheFormat(): void
    {
        self::assertCount(10, (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('EXPLAIN SELECT 1')->fields() ?? []);
        self::assertCount(12, (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT = TRADITIONAL SELECT 1')->fields() ?? []);
        self::assertFalse((new Semantics(Dialect::MySql))->analyze('EXPLAIN SELECT 1')->shape()?->complete());
        self::assertNull((new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT=JSON INTO @v SELECT 1')->shape());
    }
    public function testDeriveRefusesContradictoryIntoOptionsBeforePublication(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('EXPLAIN INTO requires FORMAT=JSON');
        (new Semantics(Dialect::MySql))->analyze('EXPLAIN INTO @v SELECT 1');
    }

}
