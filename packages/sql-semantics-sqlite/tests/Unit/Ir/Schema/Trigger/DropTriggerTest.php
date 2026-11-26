<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\DropTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger;

#[CoversClass(DropTrigger::class)]
#[Small]
final class DropTriggerTest extends TestCase
{
    public function testToStringRendersTheExplicitGuard(): void
    {
        $drop = new DropTrigger(new MissingTrigger(new Identifier('audit "orders"')), true);

        self::assertSame('DROP TRIGGER IF EXISTS "audit ""orders"""', $drop->toString());
    }

    public function testProfileIdNamesThePinnedProfile(): void
    {
        $drop = new DropTrigger(new MissingTrigger(new Identifier('audit')), true);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $drop->profileId());
    }
}
