<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\GenerationParameters;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(GenerationParameters::class)]
#[Small]
final class GenerationParametersTest extends TestCase
{
    public function testOccurrencesRetainsMarkerIdentityAndWrittenOrder(): void
    {
        $left = new M\Literal\Parameter();
        $right = new M\Literal\Parameter();
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $left, $right);
        self::assertSame([$left, $right], (new GenerationParameters())->occurrences($expression, GrammarRelease::MySql5744));
    }

    public function testCaptureSuppliesOneProtocolSlotPerMarker(): void
    {
        $binding = (new GenerationParameters())->capture(new M\Literal\Parameter(), GrammarRelease::MySql5744);
        self::assertCount(1, $binding->types);
        self::assertFalse($binding->types[0][1]);
    }

    public function testValuesRetainsDistinctOccurrencesWithTheSameMarkerSpelling(): void
    {
        $left = new M\Literal\Parameter();
        $right = new M\Literal\Parameter();
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $left, $right);
        $values = (new GenerationParameters())->values($expression, GrammarRelease::MySql5744, [[7, Domain::integer()], [null, Domain::null()]]);
        self::assertInstanceOf(M\Literal\NumberLiteral::class, $values[spl_object_id($left)]);
        self::assertSame('7', $values[spl_object_id($left)]->text);
        self::assertInstanceOf(M\Literal\NullLiteral::class, $values[spl_object_id($right)]);
    }

    public function testLiteralPreservesNumericSignAndStringIdentity(): void
    {
        $parameters = new GenerationParameters();
        $negative = $parameters->literal(-7, Domain::integer());
        $text = $parameters->literal('7', Domain::string(1, M\Type\Resolved\Collation::binary()));
        self::assertInstanceOf(M\Expression\Operator\Unary::class, $negative);
        self::assertSame(M\Expression\Operator\UnaryOperator::Minus, $negative->operator);
        self::assertInstanceOf(M\Literal\StringLiteral::class, $text);
        self::assertSame('7', $text->value());
        self::assertNotNull($text->introducer);
        self::assertSame('binary', $text->introducer->value);
    }
}
