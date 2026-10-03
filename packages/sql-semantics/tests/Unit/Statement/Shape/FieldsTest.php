<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\Fields;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\UniqueField;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Fields::class)]
#[Small]
final class FieldsTest extends TestCase
{
    public function testCountIsTheNumberOfOutputPositions(): void
    {
        $fields = new Fields([
            new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)),
            new Field(1, new OutputSlot(null, new Known(Storage::Text), Nullability::Nullable)),
        ], Comparison::Sensitive);

        self::assertCount(2, $fields);
        self::assertSame(2, $fields->count());
        self::assertCount(0, new Fields([], Comparison::Sensitive));
    }

    public function testGetIteratorWalksTheFieldsInOutputOrder(): void
    {
        $first = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $second = new Field(1, new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::Nullable));
        $fields = new Fields([$first, $second], Comparison::Sensitive);

        self::assertSame([$first, $second], iterator_to_array($fields));
        self::assertSame([$first, $second], $fields->items);
    }

    public function testAtAnswersTheFieldAtAPosition(): void
    {
        $second = new Field(1, new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::Nullable));
        $fields = new Fields([new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)), $second], Comparison::Sensitive);

        self::assertSame($second, $fields->at(1));
    }

    public function testAtRefusesAPositionOutsideTheList(): void
    {
        $fields = new Fields([new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull))], Comparison::Sensitive);

        $this->expectExceptionMessage('No field exists at the requested position.');

        $fields->at(1);
    }

    public function testLookupTellsOneNoneAndSeveralApart(): void
    {
        $first = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $second = new Field(1, new OutputSlot(new Name('A'), new Known(Storage::Text), Nullability::Nullable));
        $third = new Field(2, new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::Nullable));
        $fourth = new Field(3, new OutputSlot(null, new Known(Storage::Text), Nullability::Nullable));
        $fields = new Fields([$first, $second, $third, $fourth], Comparison::AsciiInsensitive);

        $ambiguous = $fields->lookup('a');
        $unique = $fields->lookup('B');

        self::assertInstanceOf(AmbiguousFields::class, $ambiguous);
        self::assertSame([$first, $second], $ambiguous->fields);
        self::assertInstanceOf(UniqueField::class, $unique);
        self::assertSame($third, $unique->field);
        self::assertInstanceOf(AbsentField::class, $fields->lookup('c'));
    }

    public function testLookupIsSensitiveWhenTheProfileSaysSo(): void
    {
        $first = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $second = new Field(1, new OutputSlot(new Name('A'), new Known(Storage::Text), Nullability::Nullable));
        $fields = new Fields([$first, $second], Comparison::Sensitive);

        $lookup = $fields->lookup('A');

        self::assertInstanceOf(UniqueField::class, $lookup);
        self::assertSame($second, $lookup->field);
    }

    public function testLookupIsNotReachedWhenAFieldHoldsAnotherPosition(): void
    {
        $this->expectExceptionMessage('Each field holds its own output position.');

        new Fields([new Field(1, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull))], Comparison::Sensitive);
    }
}
