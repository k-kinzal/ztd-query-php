<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AtTimeZone::class)]
#[Medium]
final class AtTimeZoneTest extends TestCase
{
    public function testDeriveScalarIsADateTimeOfThePrecision(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new AtTimeZone(new NullLiteral(), new Text('UTC'), false, '6'), $derivation->environment());

        self::assertEquals(new Known(new Temporal(TemporalKind::DateTime, '6')), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderKeepsTheIntervalKeyword(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new AtTimeZone(new NumberLiteral('1'), new Text('+00:00'), true))->render($out);

        self::assertSame("CAST(1 AT TIME ZONE INTERVAL '+00:00' AS DATETIME)", (new Lexical())->join($out->pieces()));
    }

    public function testAHexadecimalZoneIsRejected(): void
    {
        $this->expectExceptionMessage('A time zone is a quoted string.');

        new AtTimeZone(new NumberLiteral('1'), new Text('00', EscapeRule::Backslash, Radix::Hexadecimal));
    }
}
