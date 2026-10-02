<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(IntervalDesignation::class)]
#[Small]
final class IntervalDesignationTest extends TestCase
{
    public function testTypeFactIsTheRestrictedInterval(): void
    {
        self::assertEquals(new Known(Builtin::Interval), (new IntervalDesignation())->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false));
        $fact = (new IntervalDesignation(IntervalFields::HourToSecond, new IntegerConstant('12')))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('interval hour to second(6)', $fact->descriptor->name());
    }

    public function testCatalogNameIsInterval(): void
    {
        self::assertSame('interval', (new IntervalDesignation(IntervalFields::Year))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new IntervalDesignation())->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesFieldsAndPrecisionAsATypeName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntervalDesignation(IntervalFields::DayToSecond, new IntegerConstant('3')))->render($out);
        self::assertSame('INTERVAL DAY TO SECOND (3)', (new Lexical())->join($out->pieces()));
    }

    public function testConstantWritesTheFieldsAfterTheString(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntervalDesignation(IntervalFields::Day))->constant($out, new StringConstant('1'));
        self::assertSame("INTERVAL '1' DAY", (new Lexical())->join($out->pieces()));
    }

    public function testConstantWritesAPrecisionAloneBeforeTheString(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntervalDesignation(null, new IntegerConstant('2')))->constant($out, new StringConstant('1'));
        self::assertSame("INTERVAL (2) '1'", (new Lexical())->join($out->pieces()));
    }

    public function testRestrictionWritesNothingForAPlainInterval(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntervalDesignation())->restriction($out);
        self::assertSame([], $out->pieces());
    }
}
