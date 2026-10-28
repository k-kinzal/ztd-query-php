<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\GenerationParameters;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(GenerationParameters::class)]
#[Small]
final class GenerationParametersTest extends TestCase
{
    public function testOccurrencesRetainsMarkerIdentityAndWrittenOrder(): void
    {
        $left = new M\Literal\Parameter();
        $right = new M\Literal\Parameter(position: 1);
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $left, $right);
        self::assertSame([$left, $right], (new GenerationParameters())->occurrences($expression));
    }

    public function testOccurrencesUsesInputSlotsWhenOperandOrderDiffers(): void
    {
        $first = new M\Literal\Parameter(position: 0);
        $second = new M\Literal\Parameter(position: 1);
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $second, $first);
        self::assertSame([$first, $second], (new GenerationParameters())->occurrences($expression));
        $values = (new GenerationParameters())->values($expression, [[7, Domain::integer()], [9, Domain::integer()]]);
        self::assertInstanceOf(M\Literal\NumberLiteral::class, $values[spl_object_id($first)]);
        self::assertSame('7', $values[spl_object_id($first)]->text);
        self::assertInstanceOf(M\Literal\NumberLiteral::class, $values[spl_object_id($second)]);
        self::assertSame('9', $values[spl_object_id($second)]->text);
    }

    public function testOccurrencesIncludesUnreferencedCommonTableMarkers(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('WITH unused AS (SELECT ?) SELECT ?');
        $markers = (new GenerationParameters())->occurrences($statement);
        self::assertCount(2, $markers);
        self::assertSame([0, 1], array_map(static fn (M\Literal\Parameter $marker): int => $marker->position, $markers));
    }

    public function testCaptureSuppliesOneProtocolSlotPerMarker(): void
    {
        $binding = (new GenerationParameters())->capture(new M\Literal\Parameter());
        self::assertCount(1, $binding->types);
        self::assertFalse($binding->types[0][1]);
    }

    public function testValuesRetainsDistinctOccurrencesWithTheSameMarkerSpelling(): void
    {
        $left = new M\Literal\Parameter();
        $right = new M\Literal\Parameter(position: 1);
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $left, $right);
        $values = (new GenerationParameters())->values($expression, [[7, Domain::integer()], [null, Domain::null()]]);
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
