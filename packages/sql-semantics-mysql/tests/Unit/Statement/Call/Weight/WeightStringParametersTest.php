<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Weight;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(WeightStringParameters::class)]
#[Small]
final class WeightStringParametersTest extends TestCase
{
    public function testResultLengthExposesNumericMeaningIndependentlyOfSpelling(): void
    {
        $node = new WeightStringParameters(new StringLiteral(['abc']), new Numeral('A', true), new Numeral('1e2'), new Numeral('4294967424'));

        self::assertSame([10, 1, 128], [$node->resultLength(), $node->weightCount(), $node->formatFlags()]);
    }

    public function testWeightCountNarrowsAfterHexadecimalConversion(): void
    {
        $node = new WeightStringParameters(new StringLiteral(['abc']), new Numeral('0'), new Numeral('100000001', true), new Numeral('0'));

        self::assertSame(1, $node->weightCount());
    }

    public function testFormatFlagsDoesNotEvaluateAnExponentSuffix(): void
    {
        $node = new WeightStringParameters(new StringLiteral(['abc']), new Numeral('0'), new Numeral('0'), new Numeral('128e2'));

        self::assertSame(128, $node->formatFlags());
    }

    public function testDeriveScalarAnswersABinaryString(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new WeightStringParameters(new StringLiteral(['x']), new Numeral('0'), new Numeral('1'), new Numeral('2')), $derivation->environment());

        self::assertEquals(new Known(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(64, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())), $fact->type);
    }

    public function testRenderWritesTheNumbers(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new WeightStringParameters(new ColumnUse(new Name('a')), new Numeral('0'), new Numeral('1'), new Numeral('2')))->render($out);

        self::assertSame('WEIGHT_STRING(a, 0, 1, 2)', (new Lexical())->join($out->pieces()));
    }
}
