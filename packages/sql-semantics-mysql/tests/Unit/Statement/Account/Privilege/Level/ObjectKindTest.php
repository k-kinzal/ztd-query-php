<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;

#[CoversClass(ObjectKind::class)]
#[Small]
final class ObjectKindTest extends TestCase
{
    public function testKeywordSpellsTheRoutineKinds(): void
    {
        self::assertSame([null, 'FUNCTION', 'PROCEDURE'], array_map(static fn (ObjectKind $kind): ?string => $kind->keyword(), ObjectKind::cases()));
    }
}
