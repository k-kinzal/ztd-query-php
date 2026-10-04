<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\EstimateKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineEstimate;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(RoutineEstimate::class)]
#[Small]
final class RoutineEstimateTest extends TestCase
{
    public function testAlterableIsTrue(): void
    {
        self::assertTrue((new RoutineEstimate(EstimateKind::Rows, new SignedNumber(false, new IntegerConstant('5'))))->alterable());
    }

    public function testSettingNamesTheEstimate(): void
    {
        self::assertSame('rows', (new RoutineEstimate(EstimateKind::Rows, new SignedNumber(false, new IntegerConstant('5'))))->setting());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new RoutineEstimate(EstimateKind::Cost, new SignedNumber(false, new IntegerConstant('5'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesKeywordAndNumber(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineEstimate(EstimateKind::Cost, new SignedNumber(true, new IntegerConstant('5'))))->render($out);
        self::assertSame('COST - 5', (new Lexical())->join($out->pieces()));
    }
}
