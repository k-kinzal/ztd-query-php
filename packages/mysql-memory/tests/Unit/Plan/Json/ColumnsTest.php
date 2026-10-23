<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Json;

use MySqlMemory\Plan\Json\Columns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Columns::class)]
#[Small]
final class ColumnsTest extends TestCase
{
    public function testCompileKeepsResolvedSlotsAndNestedDefaultsInDeclarationOrder(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $operation = $session->analyze("SELECT * FROM JSON_TABLE('[{}]', '$[*]' COLUMNS (NESTED PATH '$' COLUMNS (n FOR ORDINALITY, a INT PATH '$.a' DEFAULT '7' ON EMPTY))) j");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement->query);
        $table = $operation->statement->query->from;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\JsonTable::class, $table);
        $slots = $operation->facts->relation($table)->shape->slots;
        $column = (new Columns())->compile($table->columns[0], $slots);
        self::assertSame(['n', 'a'], array_column($column->columns, 'name'));
        self::assertSame([], $slots);
    }
}
