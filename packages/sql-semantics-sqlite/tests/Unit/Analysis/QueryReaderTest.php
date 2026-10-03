<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\QueryReader;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(QueryReader::class)]
#[Medium]
final class QueryReaderTest extends TestCase
{
    #[TestWith(['SELECT 1', Select::class])]
    #[TestWith(['VALUES (1)', Rows::class])]
    #[TestWith(["VALUES (1, 'one'), (2, NULL)", Rows::class])]
    #[TestWith(['VALUES (1 + 2), (4 * 5)', Rows::class])]
    public function testReadKeepsTheQueryFormAndDatabaseResults(string $sql, string $class): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $parser = new SqliteParser();
        $reader = new QueryReader();
        $query = $reader->read($parser->parse($sql)->find('select')[0], $catalog);
        self::assertSame($class, $query::class);
        $graph = new SemanticGraph();
        self::assertTrue($graph->isSemanticOperation($query));
        self::assertSame($graph->fingerprint($query), $graph->fingerprint($reader->read($parser->parse($query->toString())->find('select')[0], $catalog)));
        $database = new PDO('sqlite::memory:');
        $original = $database->query($sql);
        $rebuilt = $database->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $rebuilt->fetchAll(PDO::FETCH_ASSOC));
    }
}
