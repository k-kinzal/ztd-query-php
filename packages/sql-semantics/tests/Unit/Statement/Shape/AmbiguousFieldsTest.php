<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AmbiguousFields::class)]
#[Medium]
final class AmbiguousFieldsTest extends TestCase
{
    public function testFieldsAreEveryFieldWithTheNameInOutputOrder(): void
    {
        $lookup = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS b, 3 AS a')->lookupField('a');

        self::assertInstanceOf(AmbiguousFields::class, $lookup);
        self::assertSame('a', $lookup->name);
        self::assertSame([0, 2], array_map(static fn (Field $field): int => $field->position, $lookup->fields));
    }

    public function testFieldsAreAtLeastTwo(): void
    {
        $field = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));

        $this->expectExceptionMessage('An ambiguous lookup has at least two fields.');

        new AmbiguousFields('a', [$field]);
    }
}
