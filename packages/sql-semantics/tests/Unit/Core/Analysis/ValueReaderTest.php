<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use WeakReference;

#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class ValueReaderTest extends TestCase
{
    public function testReadReleasesParserObjectsAfterLowering(): void
    {
        $parser = SqliteDialect::Sqlite->platform()->parser();
        $tree = $parser->parse('SELECT 123');
        $treeReference = WeakReference::create($tree);
        $tokenReference = WeakReference::create($tree->tokens()[1]);
        $value = SqliteDialect::Sqlite->platform()->values($parser->version())->read($tree);
        unset($tree);
        self::assertNull($treeReference->get());
        self::assertNull($tokenReference->get());
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('SELECT 123', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    public function testFromFileLoadsTheDatabasePackageVocabulary(): void
    {
        $parser = PostgreSqlDialect::PostgreSql->platform()->parser();
        $value = PostgreSqlDialect::PostgreSql->platform()->values($parser->version())->read($parser->parse('VALUES (42)'));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $value);
        self::assertSame('VALUES( 42 )', (new \SqlSemantics\Statement\Statement($value))->toString());
    }
}
