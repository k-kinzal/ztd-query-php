<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\WindowChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WindowChecks::class)]
#[Small]
final class WindowChecksTest extends TestCase
{
    public function testSpecificationReportsARangeOffsetWithoutASingleOrdering(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new WindowChecks())->specification($derivation, new WindowSpecification(null, [], [], new WindowFrame(FrameMode::Range, new FrameBound(FrameBoundKind::OffsetPreceding, new Constant(new IntegerConstant('1'))))));
        self::assertEquals([new WindowProblem(WindowProblemKind::RangeOffsetOrder)], $derivation->facts()->diagnostics);
    }

    public function testSpecificationReportsGroupsWithoutOrdering(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new WindowChecks())->specification($derivation, new WindowSpecification(null, [], [], new WindowFrame(FrameMode::Groups, new FrameBound(FrameBoundKind::CurrentRow))));
        self::assertEquals([new WindowProblem(WindowProblemKind::GroupsWithoutOrder)], $derivation->facts()->diagnostics);
    }

    public function testSpecificationLeavesARefinedWindowToTheWindowClause(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new WindowChecks())->specification($derivation, new WindowSpecification(new Name('w'), [], [], new WindowFrame(FrameMode::Groups, new FrameBound(FrameBoundKind::CurrentRow))));
        (new WindowChecks())->specification($derivation, new WindowSpecification(null, [], [new SortItem(new Constant(new IntegerConstant('1')))], new WindowFrame(FrameMode::Range, new FrameBound(FrameBoundKind::OffsetPreceding, new Constant(new IntegerConstant('1'))))));
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
