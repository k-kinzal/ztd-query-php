<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Projection\AmbiguousFields;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(AmbiguousFields::class)]
#[Small]
final class AmbiguousFieldsTest extends TestCase
{
    public function testRetainsDistinctOrderedPositionsAndIdentities(): void
    {
        $field = new Field(new NullConstant());
        self::assertSame([2 => $field, 4 => $field], (new AmbiguousFields([2 => $field, 4 => $field]))->matches);
    }

    public function testRejectsAUniqueOutputMisrepresentedAsAmbiguous(): void
    {
        $this->expectException(InvalidConstruction::class);
        new AmbiguousFields([new Field(new NullConstant())]);
    }

    public function testRejectsAnExternallyMutableOutputArray(): void
    {
        $field = new Field(new NullConstant());
        $matches = [&$field, $field];
        $this->expectException(InvalidConstruction::class);
        new AmbiguousFields($matches);
    }
}
