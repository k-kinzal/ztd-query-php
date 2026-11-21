<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(GetFormat::class)]
#[Small]
final class GetFormatTest extends TestCase
{
    public function testDeriveScalarAnswersAString(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new GetFormat(TemporalFormat::Time, new StringLiteral(['x'])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
    }

    public function testRenderWritesTheKind(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new GetFormat(TemporalFormat::Timestamp, new StringLiteral(['x'])))->render($out);

        self::assertSame("GET_FORMAT(TIMESTAMP, 'x')", (new Lexical())->join($out->pieces()));
    }
}
