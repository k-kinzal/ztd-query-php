<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(TypeAggregation::class)]
#[Small]
final class TypeAggregationTest extends TestCase
{
    public function testAggregateSkipsNullAndMergesEveryAlternative(): void
    {
        $aggregation = new TypeAggregation();

        self::assertEquals(new Known(new Decimal()), $aggregation->aggregate([new NullOnly(), new Known(new Integral(IntegralKind::Int)), new Known(new Decimal())]));
        self::assertInstanceOf(NullOnly::class, $aggregation->aggregate([new NullOnly()]));
        self::assertEquals(new Choice([new Decimal(), new Character(CharacterKind::VarChar)]), $aggregation->aggregate([new Choice([new Integral(IntegralKind::Int), new Character(CharacterKind::Char)]), new Known(new Decimal())]));
    }

    public function testDistinctKeepsTheFirstOfEqualTypes(): void
    {
        self::assertEquals([new Decimal('5'), new Integral(IntegralKind::Int)], (new TypeAggregation())->distinct([new Decimal('5'), new Integral(IntegralKind::Int), new Decimal()]));
    }

    public function testMergeFollowsTheTypeFamilies(): void
    {
        $aggregation = new TypeAggregation();
        $json = new Elementary(ElementaryKind::Json);

        self::assertEquals(
            [new Character(CharacterKind::LongText), new Spatial(SpatialKind::Geometry), new Binary(BinaryKind::Blob), new Character(CharacterKind::VarChar), new Temporal(TemporalKind::DateTime), $json],
            [
                $aggregation->merge($json, new Integral(IntegralKind::Int)),
                $aggregation->merge(new Spatial(SpatialKind::Point), new Spatial(SpatialKind::Polygon)),
                $aggregation->merge(new Binary(BinaryKind::VarBinary), new Character(CharacterKind::Text)),
                $aggregation->merge(new Character(CharacterKind::Char), new Integral(IntegralKind::Int)),
                $aggregation->merge(new Temporal(TemporalKind::Date), new Temporal(TemporalKind::Timestamp)),
                $aggregation->merge($json, new Elementary(ElementaryKind::Json)),
            ],
        );
    }

    public function testJsonRecognisesTheJsonType(): void
    {
        self::assertSame([true, false], [(new TypeAggregation())->json(new Elementary(ElementaryKind::Json)), (new TypeAggregation())->json(new Elementary(ElementaryKind::Bit))]);
    }

    public function testTextualRecognisesCharacterStrings(): void
    {
        self::assertSame([true, false], [(new TypeAggregation())->textual(new Character(CharacterKind::Char)), (new TypeAggregation())->textual(new Binary(BinaryKind::Blob))]);
    }

    public function testBinaryTakesTheLargerBlob(): void
    {
        self::assertEquals(new Binary(BinaryKind::LongBlob), (new TypeAggregation())->binary(new Binary(BinaryKind::TinyBlob), new Character(CharacterKind::LongText)));
    }

    public function testCharacterTakesTheLargerText(): void
    {
        self::assertEquals(new Character(CharacterKind::MediumText), (new TypeAggregation())->character(new Character(CharacterKind::Long), new Character(CharacterKind::TinyText)));
    }

    public function testTextSizeRanksTheTextTypes(): void
    {
        self::assertSame([0, 1, 4], [(new TypeAggregation())->textSize(new Character(CharacterKind::VarChar)), (new TypeAggregation())->textSize(new Character(CharacterKind::TinyText)), (new TypeAggregation())->textSize(new Character(CharacterKind::LongText))]);
    }
}
