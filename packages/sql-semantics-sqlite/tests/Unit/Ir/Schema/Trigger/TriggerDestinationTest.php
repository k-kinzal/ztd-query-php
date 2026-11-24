<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerDestination::class)]
#[Small]
final class TriggerDestinationTest extends TestCase
{
    public function testCopiesWritablePositionsWithoutRetainingArrayReferences(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('id'), new Identifier('value')]);
        $position = 0;
        $destination = new TriggerDestination($table, [&$position]);
        $position = 1;

        self::assertSame($table, $destination->table);
        self::assertSame([0], $destination->writable);
    }
}
