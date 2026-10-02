<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\CaseInputReader::class)]
#[Small]
final class CaseInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE 1 WHEN 1 THEN 7 ELSE 9 END')->find('expr')[0];
        $input = (new I\CaseInputReader())->read($source, new I\ExpressionInputReader());
        self::assertNotNull($input);
        self::assertSame('CASE 1 WHEN 1 THEN 7 ELSE 9 END', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testArmsPreservesWhenThenPairingAndOrder(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE WHEN 1 THEN 7 WHEN 2 THEN 8 END')->find('case_exprlist')[0];
        $arms = (new I\CaseInputReader())->arms($source, new I\ExpressionInputReader());
        self::assertCount(2, $arms);
        $branches = new C\Conditional\CaseBranchesInput(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), ...$arms);
        self::assertSame('WHEN 1 THEN 7 WHEN 2 THEN 8', (new C\Rendering\ExpressionSql())->branches($branches));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNameSensitiveCases')]
    public function testReadDoesNotCaptureAnExistingAliasWhenKeepingACaseOutputName(string $expression): void
    {
        $quoted = '"' . str_replace('"', '""', $expression) . '"';
        $sql = 'SELECT ' . $expression . ', 0 AS ' . $quoted . ' WHERE ' . $quoted;
        $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')));
        $source = (new SqliteParser())->parse($sql)->find('select')[0];
        $query = (new \SqlSemantics\Platform\Sqlite\Analysis\SelectReader())->read($source, $catalog);
        self::assertNull($query->fields()->items[0]->alias);
        self::assertSame($expression, $query->fields()->items[0]->name->value);
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\AliasReference::class, $query->where);
        self::assertSame($query->fields()->items[1], $query->where->field);
        $database = new PDO('sqlite::memory:');
        $original = $database->query($sql);
        $rebuilt = $database->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame([], $original->fetchAll(PDO::FETCH_NUM));
        self::assertSame([], $rebuilt->fetchAll(PDO::FETCH_NUM));
        self::assertSame($original->getColumnMeta(0), $rebuilt->getColumnMeta(0));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerNameSensitiveCases(): array
    {
        return [
            ['case when 0 then 0 else 1 end'],
            ['case 0 when 0 then 1 else 0 end'],
            ['cAsE/*base*/0/*when*/wHeN/*test*/0/*then*/tHeN/*result*/1/*else*/eLsE/*fallback*/0/*end*/eNd'],
            ["case when'0'then'0'else'1'end"],
            ["case\nwhen 0 then 0\nwhen 1 then 1\nend"],
            ['case when 1 then case 0 when 0 then 1 end else 0 end'],
            ['case when 1+2=3 then 1 else 0 end'],
            ['case when not 0 then +1 else -1 end'],
            ['case -(1) when -1 then 1 else 0 end'],
        ];
    }
}
