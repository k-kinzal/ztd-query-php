<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable::class)]
#[Medium]
final class JsonTableTest extends TestCase
{
    public function testDeriveRelationFlattensNestedColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' AS p PASSING 1 AS x COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v' NULL ON EMPTY, f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER, e boolean EXISTS PATH '\$.e' ERROR ON ERROR, NESTED '\$.b[*]' AS q COLUMNS (b text)) ERROR ON ERROR) AS j");
        self::assertSame(['n:NotNull', 'v:Nullable', 'f:Nullable', 'e:Nullable', 'b:Nullable'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): string => ($field->name->value ?? '') . ':' . $field->nullability->name, $query->fields()->items ?? []));
    }

    public function testDeriveRelationReportsAPathThatIsNoConstant(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', 1 + 1 COLUMNS (n FOR ORDINALITY))");
        self::assertSame('only string constants are supported in JSON_TABLE path specification', $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesEveryClause(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' AS p PASSING 1 AS x COLUMNS (n FOR ORDINALITY, v integer PATH '\$.v' NULL ON EMPTY, f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER, e boolean EXISTS PATH '\$.e' ERROR ON ERROR, NESTED '\$.b[*]' AS q COLUMNS (b text)) ERROR ON ERROR) AS j");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' AS p PASSING 1 AS x COLUMNS (n FOR ORDINALITY, v INTEGER PATH '\$.v' NULL ON EMPTY, f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER, e BOOLEAN EXISTS PATH '\$.e' ERROR ON ERROR, NESTED PATH '\$.b[*]' AS q COLUMNS (b text)) ERROR ON ERROR) AS j", $query->toString());
    }
}
