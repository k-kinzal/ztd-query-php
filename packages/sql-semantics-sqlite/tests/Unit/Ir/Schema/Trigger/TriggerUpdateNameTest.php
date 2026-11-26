<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerUpdateName;

#[CoversClass(TriggerUpdateName::class)]
#[Small]
final class TriggerUpdateNameTest extends TestCase
{
    public function testDistinguishesAnExplicitSlotFromAnAbsentName(): void
    {
        $name = new Identifier('later');

        self::assertSame(1, (new TriggerUpdateName(1))->selector);
        self::assertSame($name, (new TriggerUpdateName($name))->selector);
    }
}
