<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerMutationScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetNullTest;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerMutationScope::class)]
#[Small]
final class TriggerMutationScopeTest extends TestCase
{
    public function testFilterAcceptsAReadOnlyDeclaredField(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('id'), new Identifier('derived')]);
        $target = new TriggerDestination($table, [0]);
        $filter = new TriggerTargetNullTest(new TriggerTargetField(1), true);

        (new TriggerMutationScope())->filter($target, $filter);

        self::assertSame(1, $filter->target->column);
    }

    public function testLocalFieldRejectsAnUnknownPosition(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('x')]);
        $this->expectExceptionMessage('must read a declared position of its destination');

        (new TriggerMutationScope())->localField(new TriggerDestination($table, [0]), new TriggerTargetField(1));
    }
}
