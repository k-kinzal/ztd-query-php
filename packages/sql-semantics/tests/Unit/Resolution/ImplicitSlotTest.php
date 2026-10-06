<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ImplicitSlot::class)]
#[Small]
final class ImplicitSlotTest extends TestCase
{
    public function testEveryNameLeadsToTheOneSlot(): void
    {
        $slot = new OutputSlot(new Name('rowid'), new Known(Storage::Integer), Nullability::NotNull);

        $implicit = new ImplicitSlot([new Name('rowid'), new Name('oid')], $slot);

        self::assertSame(['rowid', 'oid'], array_map(static fn (Name $name): string => $name->value, $implicit->names));
        self::assertSame($slot, $implicit->slot);
    }
}
