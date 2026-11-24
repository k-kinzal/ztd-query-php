<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;

#[CoversClass(TriggerTargetField::class)]
#[Small]
final class TriggerTargetFieldTest extends TestCase
{
    public function testColumnIsTheResolvedLocalIndex(): void
    {
        self::assertSame(2, (new TriggerTargetField(2))->column);
    }
}
