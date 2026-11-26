<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerReference;

#[CoversClass(TriggerReference::class)]
#[Small]
final class TriggerReferenceTest extends TestCase
{
    public function testQuotedUsesTriggerPlacementInsteadOfTheWatchedSchema(): void
    {
        $trigger = new TriggerReference(new Identifier('temp'), new Identifier('audit'), new Identifier('main'), new Identifier('orders'));

        self::assertSame('"temp"."audit"', $trigger->quoted());
    }
}
