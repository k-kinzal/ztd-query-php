<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\RelationKind;

#[CoversClass(RelationKind::class)]
#[Small]
final class RelationKindTest extends TestCase
{
    public function testEveryKindOfRelationIsACase(): void
    {
        self::assertSame(['BaseTable', 'View', 'MaterializedView', 'ForeignTable', 'Sequence'], array_map(static fn (RelationKind $kind): string => $kind->name, RelationKind::cases()));
    }
}
