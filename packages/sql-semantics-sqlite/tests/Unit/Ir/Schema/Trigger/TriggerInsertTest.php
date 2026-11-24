<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerInsert::class)]
#[Small]
final class TriggerInsertTest extends TestCase
{
    public function testKeepsTheExplicitAssignmentOrder(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('a'), new Identifier('b')]);
        $target = new TriggerDestination($table, [0, 1]);
        $last = new TriggerInsertField(1, new NullLiteral());
        $first = new TriggerInsertField(0, new NullLiteral());
        $program = new TriggerInsert($target, [$last, $first]);

        self::assertSame([$last, $first], $program->fields);
        self::assertSame('inherit', $program->conflict->value);
    }
}
