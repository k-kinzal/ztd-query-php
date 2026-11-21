<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Resolution\ImplicitSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

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
