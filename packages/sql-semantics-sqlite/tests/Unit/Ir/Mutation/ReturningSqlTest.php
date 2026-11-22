<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturningSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(ReturningSql::class)]
#[Small]
final class ReturningSqlTest extends TestCase
{
    public function testClauseKeepsTheOrderedUnqualifiedFieldsAndLabels(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('entries'), [new Identifier('id'), new Identifier('a"b')]);
        $columns = [
            new ReturnedColumn(new ColumnReference(1), new Identifier('result')),
            new ReturnedColumn(new ColumnReference(0), new Identifier('id')),
            new ReturnedColumn(new ColumnReference(1), new Identifier('copy')),
        ];

        self::assertSame(' RETURNING "a""b" AS "result", "id" AS "id", "a""b" AS "copy"', (new ReturningSql())->clause($target, $columns));
    }
}
