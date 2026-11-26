<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\Update
 */
final class UpdateTest extends TestCase
{
    public function testProfileIdUsesTheRecordedSqliteProfile(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('records'), [new Identifier('item')]);
        $update = new Update($target, [new UpdateAssignment(0, new Parameter('3'))]);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $update->profileId());
    }

    public function testToStringPreservesTheFirstLabelAndBothWrites(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('records'), [new Identifier('item')]);
        $update = new Update(
            $target,
            [new UpdateAssignment(0, new Parameter('03')), new UpdateAssignment(0, new Parameter('3'))],
            conflict: UpdateConflict::Ignore,
        );

        self::assertSame('UPDATE OR IGNORE "main"."records" AS "__ztd_source" SET "item" = ?03, "item" = ?3', $update->toString());
    }

    public function testToStringReturnsTheGeneratedSlotInTheTargetScope(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('records'), [new Identifier('item'), new Identifier('computed')]);
        $column = new ReturnedColumn(new ColumnReference(1), new Identifier('value'));
        $update = new Update($target, [new UpdateAssignment(0, new Parameter('3'))], returning: [$column]);

        self::assertSame('UPDATE "main"."records" AS "__ztd_source" SET "item" = ?3 RETURNING "computed" AS "value"', $update->toString());
    }
}
