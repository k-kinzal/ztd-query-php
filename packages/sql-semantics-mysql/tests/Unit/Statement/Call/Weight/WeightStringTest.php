<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Weight;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(WeightString::class)]
#[Small]
final class WeightStringTest extends TestCase
{
    public function testDeriveScalarAnswersABinaryString(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new WeightString(new StringLiteral(['x'])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Binary->descriptor()), $fact->type);
    }

    public function testRenderWritesTheCastAndTheLevels(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new WeightString(new ColumnUse(new Name('a')), WeightCast::Char, new Numeral('4'), [new WeightLevel(new Numeral('1')), new WeightLevel(new Numeral('2'), Direction::Descending)]))->render($out);

        self::assertSame('WEIGHT_STRING(a AS CHAR(4) LEVEL 1, 2 DESC)', (new Lexical())->join($out->pieces()));
    }

}
