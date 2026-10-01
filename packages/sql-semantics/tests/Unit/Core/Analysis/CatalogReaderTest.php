<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Analysis\CatalogReader;
use SqlSemantics\Platform\Sqlite\Analysis\OperationReader;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\AmbiguousTable;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Script\Sequence;

#[CoversClass(CatalogReader::class)]
#[Medium]
final class CatalogReaderTest extends TestCase
{
    /**
     * @param list<int> $order
     */
    #[TestWith([[0, 1, 2]])]
    #[TestWith([[0, 2, 1]])]
    #[TestWith([[1, 0, 2]])]
    #[TestWith([[1, 2, 0]])]
    #[TestWith([[2, 0, 1]])]
    #[TestWith([[2, 1, 0]])]
    public function testTablesKeepOriginalDeclarationsRegardlessOfSchemaOperationOrder(array $order): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $create = $reader->read($parser->parse('CREATE TABLE bar (foo INTEGER)'), $catalog);
        self::assertInstanceOf(SqliteCreateTable::class, $create);
        $operations = [$create, $reader->read($parser->parse('ALTER TABLE bar RENAME COLUMN foo TO changed'), $catalog), $reader->read($parser->parse('DROP TABLE bar'), $catalog)];
        $dependencies = array_map(static fn (int $position) => $operations[$position], $order);
        $tables = (new CatalogReader())->tables(new Sequence(...$dependencies));
        self::assertSame([$create->table], $tables);
        $context = new Catalog($catalog->searchPath, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, ...$tables);
        $query = $reader->read($parser->parse('SELECT foo FROM bar'), $context);
        self::assertInstanceOf(Select::class, $query);
        $reference = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertInstanceOf(ResolvedColumn::class, $reference->resolution);
        self::assertSame($create->table, $reference->resolution->table);
        self::assertSame($create->columns[0]->column, $reference->resolution->column);
    }

    public function testTablesPreserveConflictingDeclarationsWithoutChoosingByOrder(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $parser = new SqliteParser();
        $reader = new OperationReader();
        $first = $reader->read($parser->parse('CREATE TABLE bar (foo INTEGER)'), $catalog);
        $second = $reader->read($parser->parse('CREATE TABLE IF NOT EXISTS bar (foo TEXT)'), $catalog);
        self::assertInstanceOf(SqliteCreateTable::class, $first);
        self::assertInstanceOf(SqliteCreateTable::class, $second);
        $tables = (new CatalogReader())->tables($first, $first->table, $first, $second);
        self::assertSame([$first->table, $second->table], $tables);
        $context = new Catalog($catalog->searchPath, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, ...$tables);
        $query = $reader->read($parser->parse('SELECT foo FROM bar'), $context);
        self::assertInstanceOf(Select::class, $query);
        $reference = $query->field('foo')->expression;
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertInstanceOf(AmbiguousTable::class, $reference->resolution);
        self::assertSame($tables, $reference->resolution->relations[0]->declarations);
    }

    public function testTablesDoNotExposeDeclarationsInsideInspectionRequests(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $request = (new OperationReader())->read((new SqliteParser())->parse('EXPLAIN CREATE TABLE bar (foo INTEGER)'), $catalog);
        self::assertSame([], (new CatalogReader())->tables($request));
    }
}
