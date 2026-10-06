<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTableColumn::class)]
#[Medium]
final class JsonTableColumnTest extends TestCase
{
    public function testColumnsImplementTheContract(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' AS p PASSING 1 AS x COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v' NULL ON EMPTY, f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER, e boolean EXISTS PATH '\$.e' ERROR ON ERROR, NESTED '\$.b[*]' AS q COLUMNS (b text)) ERROR ON ERROR) AS j");
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $from = $select->from;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable::class, $from);
        self::assertCount(5, $from->columns);
    }
}
