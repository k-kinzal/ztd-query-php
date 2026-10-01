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
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
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

    public function testStatementGivesTheOuterCommentsToTheStatementAndTheInnerOnesToValues(): void
    {
        $parser = SqliteDialect::Sqlite->platform()->parser();
        $statement = SqliteDialect::Sqlite->platform()->values($parser->version())->statement($parser->parse('/* lead */ SELECT /* a */ 1 -- end'));
        self::assertSame(['/* lead */'], $statement->comments->before(\SqlSemantics\Statement\Statement::BEFORE));
        self::assertSame(['-- end'], $statement->comments->before(\SqlSemantics\Statement\Statement::AFTER));
        self::assertSame('/* lead */ SELECT /* a */ 1 -- end', $statement->toString());
        self::assertSame('SELECT /* a */ 1', \SqlSemantics\Statement\Writer::render($statement->command));
    }

    public function testLowerKeepsACommentInTheOutermostValueWhoseSymbolItPrecedes(): void
    {
        $parser = SqliteDialect::Sqlite->platform()->parser();
        $tree = $parser->parse('SELECT foo /* a */ FROM items');
        $reader = SqliteDialect::Sqlite->platform()->values($parser->version());
        $command = $reader->lower($tree, (new \SqlSemantics\Core\Analysis\TriviaReader())->read($tree), true);
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\EcmdWithCmdxSemi_b7577a8f::class, $command);
        $select = $command->cmdx;
        self::assertInstanceOf(\SqlSemantics\Statement\Model\Sqlite\Value\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptOrderbyOptLimitOpt_218e0475::class, $select);
        self::assertSame([3], $select->comments->positions());
        self::assertSame(['/* a */'], $select->comments->before(3));
        self::assertSame('SELECT foo /* a */ FROM items', \SqlSemantics\Statement\Writer::render($command));
        self::assertSame('SELECT foo FROM items', \SqlSemantics\Statement\Writer::render($command->withCmdx($select->withComments(new \SqlSemantics\Statement\Comments()))));
    }
}
