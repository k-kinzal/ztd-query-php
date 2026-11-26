<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerMutationSql;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetNullTest;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TriggerMutationSql::class)]
#[Small]
final class TriggerMutationSqlTest extends TestCase
{
    public function testActionEmitsDeletionAndItsCheckedCondition(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('entry')]);
        $target = new TriggerDestination($table, [0]);
        $step = new TriggerDelete($target, new TriggerTargetNullTest(new TriggerTargetField(0), false));

        self::assertSame('DELETE FROM "audit" WHERE "entry" IS NOT NULL', (new TriggerMutationSql())->action($step, $table));
    }

    public function testConditionEmitsNothingWhenTheActionHasNoFilter(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('entry')]);
        self::assertSame('', (new TriggerMutationSql())->condition(null, $table, $table));
    }

    public function testEqualityKeepsTheImageOnItsWrittenSide(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('entry')]);
        $test = new TriggerEquality(new TriggerTargetField(0), new TriggerField(RowImage::Old, 0), false);

        self::assertSame('(OLD."entry" = "entry")', (new TriggerMutationSql())->equality($test, $table, $table));
    }

    public function testLocalQuotesTheCheckedName(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('audit'), [new Identifier('entry')]);
        self::assertSame('"entry"', (new TriggerMutationSql())->local(new TriggerTargetField(0), $table));
    }
}
