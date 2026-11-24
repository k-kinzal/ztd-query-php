<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Trigger;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;

#[CoversClass(Trigger::class)]
#[Small]
final class TriggerTest extends TestCase
{
    public function testKeepsTheExactWatchedCoordinate(): void
    {
        $watched = new Name('Watched Orders');
        $trigger = new Trigger(new Name('temp'), new Name('audit'), new Name('workspace'), $watched);

        self::assertSame($watched, $trigger->watchedName);
        self::assertSame('workspace', $trigger->watchedSchema->value);
    }
}
