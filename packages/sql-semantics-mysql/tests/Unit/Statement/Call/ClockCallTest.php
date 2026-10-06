<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ClockCall::class)]
#[Small]
final class ClockCallTest extends TestCase
{
    public function testDeriveScalarAnswersATemporalTypeThatIsNeverNull(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new ClockCall(Clock::CurrentTime, new Numeral('2')), $derivation->environment());

        self::assertEquals(new Known(new \SqlSemantics\Platform\MySql\Statement\Type\Temporal(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Time, '2')), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesEmptyParenthesesWithoutAPrecision(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new ClockCall(Clock::Now))->render($out);

        self::assertSame('NOW()', (new Lexical())->join($out->pieces()));
    }
}
