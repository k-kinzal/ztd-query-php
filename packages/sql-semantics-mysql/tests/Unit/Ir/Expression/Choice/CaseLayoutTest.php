<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\CaseLayout;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(CaseLayout::class)]
#[Small]
final class CaseLayoutTest extends TestCase
{
    public function testResolveChecksTheReleaseSpecificLayout(): void
    {
        $source = new TableSource(
            new Identifier('app'),
            new Identifier('r'),
            [new Identifier('n')],
            [new IntegralSlot(IntegerStorage::Medium, false)]
        );
        $case = new SignedCase(
            [new SearchedCaseArm(new BooleanLiteral(true), new RangeIntegerLiteral('2147483648'))],
            otherwise: new IntegralColumnReference(0)
        );
        $layout = new CaseLayout();

        self::assertSame(
            ['storage' => IntegerStorage::Standard, 'nullable' => false],
            $layout->resolve($case, GrammarRelease::MySql5651, $source)
        );
        self::assertSame(
            ['storage' => IntegerStorage::Wide, 'nullable' => false],
            $layout->resolve($case, GrammarRelease::MySql910, $source)
        );
    }

    public function testResultAccountsForLegacyNullableDecisions(): void
    {
        $case = new SignedCase(
            [new SearchedCaseArm(new NullLiteral(), new RangeIntegerLiteral('7'))],
            otherwise: new RangeIntegerLiteral('5')
        );
        $layout = new CaseLayout();

        self::assertSame([IntegerStorage::Wide, true], $layout->result($case, GrammarRelease::MySql5651, []));
        self::assertSame([IntegerStorage::Wide, false], $layout->result($case, GrammarRelease::MySql5744, []));
    }

    public function testOccurrencesRetainsTheOuterNullExtension(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('r'), [new Identifier('n')]);
        $join = new Join(JoinKind::Left, $source, $source, new BooleanLiteral(false));

        self::assertSame([[$source, false], [$source, true]], (new CaseLayout())->occurrences($join));
    }

    public function testFieldCombinesStorageAndScopeNullability(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('r'), [new Identifier('n')], [ColumnKind::RequiredSignedInt]);

        self::assertSame([IntegerStorage::Standard, true], (new CaseLayout())->field(new ColumnReference(0), [[$source, true]]));
    }

    public function testOperandClassifiesAStandaloneNull(): void
    {
        self::assertSame([null, true], (new CaseLayout())->operand(new NullLiteral(), GrammarRelease::MySql910, []));
    }

    public function testMembershipNullableUsesTheTypedOperands(): void
    {
        $atom = new RangeIntegerLiteral('1');
        $set = new IntegerMembership($atom, [$atom, new NullLiteral()]);

        self::assertTrue((new CaseLayout())->membershipNullable($set, GrammarRelease::MySql5651, []));
    }

    public function testMergeReproducesTheOrderedLegacyTypeRule(): void
    {
        $layout = new CaseLayout();
        self::assertSame(IntegerStorage::Wide, $layout->merge(null, IntegerStorage::Medium, true));
        self::assertSame(IntegerStorage::Medium, $layout->merge(IntegerStorage::Medium, null, true));
    }
}
