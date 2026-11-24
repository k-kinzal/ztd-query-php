<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerActions;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerActions::class)]
#[Small]
final class TriggerActionsTest extends TestCase
{
    public function testCopyDetachesArrayEntriesFromCallerReferences(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('x')]);
        $first = new TriggerDelete(new TriggerDestination($table, [0]));
        $selected = $first;
        $written = [&$selected];
        $checked = (new TriggerActions())->copy($written);
        $selected = new TriggerDelete($first->destination);

        self::assertSame($first, $checked[0]);
        self::assertSame($selected, $written[0]);
    }
}
