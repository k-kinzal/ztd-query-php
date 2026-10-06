<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Refusal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Statement\Declaration\RelationKind;

#[CoversClass(KindRefusal::class)]
#[Small]
final class KindRefusalTest extends TestCase
{
    public function testRequiredIsAViewForDropViewAndInsteadOfTriggersAndATableOtherwise(): void
    {
        $views = array_values(array_filter(KindRefusal::cases(), static fn (KindRefusal $refusal): bool => $refusal->required() === RelationKind::View));

        self::assertSame([KindRefusal::DropView, KindRefusal::InsteadOfTrigger], $views);
        self::assertSame(RelationKind::BaseTable, KindRefusal::Upsert->required());
    }
}
