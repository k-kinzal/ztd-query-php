<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturningSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(ReturningSql::class)]
#[Small]
final class ReturningSqlTest extends TestCase
{
    public function testClausePreservesDuplicateLabelsAndPositions(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a'), new Identifier('b')]);
        $label = new Identifier('side"');
        $returning = [new ReturnedColumn(new ColumnReference(1), $label), new ReturnedColumn(new ColumnReference(0), $label)];

        self::assertSame(' RETURNING "b" AS "side""", "a" AS "side"""', (new ReturningSql())->clause($target, $returning));
        self::assertSame('', (new ReturningSql())->clause($target, []));
    }
}
