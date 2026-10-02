<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\LimitReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(LimitReader::class)]
#[Medium]
final class LimitReaderTest extends TestCase
{
    #[TestWith(['LIMIT 2 OFFSET 1'])]
    #[TestWith(['LIMIT 1, 2'])]
    public function testReadAssignsTheCountAndOffsetByMeaning(string $clause): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $source = (new SqliteParser())->parse('SELECT 1 ' . $clause)->find('limit_opt')[0];
        $limit = (new LimitReader())->read($source, $catalog);
        self::assertInstanceOf(SqliteInteger::class, $limit->count);
        self::assertInstanceOf(SqliteInteger::class, $limit->offset);
        self::assertSame('2', $limit->count->value->value());
        self::assertSame('1', $limit->offset->value->value());
        self::assertSame($clause, $limit->toString());
        self::assertTrue((new SemanticGraph())->containsOnlyValues($limit));
    }

    public function testReadDoesNotMakeTheQueriesInputColumnsVisibleToTheLimit(): void
    {
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer)));
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
        $source = (new SqliteParser())->parse('SELECT foo FROM bar LIMIT foo')->find('limit_opt')[0];
        $limit = (new LimitReader())->read($source, $catalog);
        self::assertInstanceOf(ColumnReference::class, $limit->count);
        self::assertSame(Invalid::MissingColumn, $limit->count->type());
        self::assertSame([], $limit->scope->tables);
        self::assertSame($catalog, $limit->scope->catalog);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE bar(foo INTEGER)');
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such column: foo');
        $db->query('SELECT foo FROM bar ' . $limit->toString());
    }
}
