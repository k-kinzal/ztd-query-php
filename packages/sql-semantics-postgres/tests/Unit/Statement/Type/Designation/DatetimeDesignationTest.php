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
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ZoneOption;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(DatetimeDesignation::class)]
#[Small]
final class DatetimeDesignationTest extends TestCase
{
    public function testBuiltinFollowsTheZoneClause(): void
    {
        self::assertSame(Builtin::Timestamptz, (new DatetimeDesignation(DatetimeKeyword::Timestamp, null, ZoneOption::WithTimeZone))->builtin());
        self::assertSame(Builtin::Timestamp, (new DatetimeDesignation(DatetimeKeyword::Timestamp, null, ZoneOption::WithoutTimeZone))->builtin());
        self::assertSame(Builtin::Timetz, (new DatetimeDesignation(DatetimeKeyword::Time, null, ZoneOption::WithTimeZone))->builtin());
        self::assertSame(Builtin::Time, (new DatetimeDesignation(DatetimeKeyword::Time))->builtin());
    }

    public function testTypeFactReducesAPrecisionAboveSix(): void
    {
        $fact = (new DatetimeDesignation(DatetimeKeyword::Time, new IntegerConstant('9')))->typeFact((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true), false);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame('time(6) without time zone', $fact->descriptor->name());
    }

    public function testCatalogNameIsTheNameInTheCatalog(): void
    {
        self::assertSame('timestamptz', (new DatetimeDesignation(DatetimeKeyword::Timestamp, null, ZoneOption::WithTimeZone))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new DatetimeDesignation(DatetimeKeyword::Time))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesPrecisionAndZone(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DatetimeDesignation(DatetimeKeyword::Timestamp, new IntegerConstant('3'), ZoneOption::WithoutTimeZone))->render($out);
        self::assertSame('TIMESTAMP (3) WITHOUT TIME ZONE', (new Lexical())->join($out->pieces()));
    }
}
