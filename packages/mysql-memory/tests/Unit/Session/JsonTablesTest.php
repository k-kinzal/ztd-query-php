<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Session\JsonTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;

#[CoversClass(JsonTables::class)]
#[Small]
final class JsonTablesTest extends TestCase
{
    public function testFirstAnswersThePathErrorOfAJsonTableOfTheOutermostTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $update = (new JsonTables())->first($session->analyze("UPDATE JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j, t SET t.a = 1")->statement);
        $column = (new JsonTables())->first($session->analyze("SELECT * FROM t JOIN JSON_TABLE('[]', '$' COLUMNS (q INT PATH '$[')) AS j ON 1")->statement);

        self::assertSame([3143, 'Invalid JSON path expression. The error is around character position 1.'], [$update?->getCode(), $update?->getMessage()]);
        self::assertSame('Invalid JSON path expression. The error is around character position 2.', $column?->getMessage());
        self::assertNull((new JsonTables())->first($session->analyze("SELECT (SELECT 1 FROM JSON_TABLE('[]', 'bad' COLUMNS (q FOR ORDINALITY)) AS j)")->statement));
        self::assertNull((new JsonTables())->first($session->analyze("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (q FOR ORDINALITY)) AS j")->statement));
    }

    public function testFunctionsAnswersTheJsonTablesOfTableReferencesInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $select = $session->analyze("SELECT 1 FROM (JSON_TABLE('[]', '$' COLUMNS (p FOR ORDINALITY)) AS x JOIN t ON 1), { OJ t LEFT JOIN JSON_TABLE('[]', '$' COLUMNS (q FOR ORDINALITY)) AS y ON 1 }")->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);

        self::assertSame(['x', 'y'], array_map(static fn (JsonTable $table): ?string => $table->alias?->value, (new JsonTables())->functions($select->from)));
    }

    public function testPathsAnswersThePathsOfTheColumnsNestedOnesIncluded(): void
    {
        $session = (new Instance())->connect();
        $select = $session->analyze("SELECT * FROM JSON_TABLE('[]', '$' COLUMNS (a INT PATH '$.a', NESTED PATH '$.b[*]' COLUMNS (c INT PATH '$.c', d FOR ORDINALITY))) AS j")->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(JsonTable::class, $select->from);

        self::assertSame(['$.a', '$.b[*]', '$.c'], (new JsonTables())->paths($select->from->columns));
    }
}
