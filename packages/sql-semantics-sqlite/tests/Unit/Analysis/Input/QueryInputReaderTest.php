<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\QueryInputReader::class)]
#[Small]
final class QueryInputReaderTest extends TestCase
{
    public function testReadRetainsAllSelectInputsWithoutBindingAColumn(): void
    {
        $source = (new SqliteParser())->parse('SELECT x.id FROM items AS x WHERE x.id > 2 LIMIT 1')->find('select')[0];
        $input = (new I\QueryInputReader())->read($source);
        self::assertSame('SELECT x.id FROM items AS x WHERE x.id > 2 LIMIT 1', (new C\Rendering\QuerySql())->write($input));
    }

    public function testRowsPreservesUnequalWidthsForLaterDiagnostics(): void
    {
        $source = (new SqliteParser())->parse('VALUES (1), (2, 3)')->find('mvalues')[0];
        $input = (new I\QueryInputReader())->rows($source);
        self::assertSame('VALUES (1), (2, 3)', (new C\Rendering\QuerySql())->write($input));
    }

    public function testProjectionKeepsDuplicateOutputPositions(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1 AS same, 2 AS same')->find('selcollist')[0];
        $fields = (new I\QueryInputReader())->projection($source);
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(...$fields));
        self::assertSame('SELECT 1 AS same, 2 AS same', (new C\Rendering\QuerySql())->write($input));
    }

    public function testTablesKeepsSeparateSelfJoinOccurrences(): void
    {
        $source = (new SqliteParser())->parse('SELECT a.id FROM items a, items b')->find('seltablist')[0];
        $inputs = (new I\QueryInputReader())->tables($source);
        self::assertCount(2, $inputs);
        self::assertSame('a', $inputs[0]->alias?->value);
        self::assertSame('b', $inputs[1]->alias?->value);
        self::assertNotSame($inputs[0], $inputs[1]);
    }

    public function testLimitRetainsTheCountAndOffsetRoles(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1 LIMIT 3, 2')->find('limit_opt')[0];
        $limit = (new I\QueryInputReader())->limit($source);
        self::assertNotNull($limit->offset);
        self::assertSame('3', (new C\Rendering\ExpressionSql())->write($limit->offset));
        self::assertSame('2', (new C\Rendering\ExpressionSql())->write($limit->count));
    }
}
