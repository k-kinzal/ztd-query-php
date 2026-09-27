<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(Analyzer::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class AnalyzerTest extends TestCase
{
    public function testAnalyzeDoesNotNeedTableDeclarations(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['main']);
        $statement = $analyzer->analyze('DROP TABLE no_such_table');
        self::assertSame('DROP TABLE no_such_table', $statement->toString());
        self::assertNotSame($statement, $analyzer->analyze('DROP TABLE no_such_table'));
    }

    public function testAnalyzeRejectsInvalidSqlWithoutAnIncompleteStatement(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyze('SELECT FROM');
    }

    public function testAnalyzeAllGivesOneStatementPerScriptStatement(): void
    {
        $statements = (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyzeAll("SELECT 1; SELECT ';' -- done");
        self::assertCount(2, $statements);
        self::assertSame('SELECT 1 ;', $statements[0]->toString());
        self::assertSame("SELECT ';' -- done", $statements[1]->toString());
    }

    public function testAnalyzeResolvesAnUnresolvedDependencyAgainstTheDependenciesBeforeIt(): void
    {
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite), ['main']);
        $users = $analyzer->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $orders = $analyzer->analyze('CREATE TABLE orders (id INTEGER, user_id INTEGER REFERENCES users (id))');
        $query = $analyzer->analyze('SELECT * FROM orders', [$users, $orders]);
        self::assertSame($orders, $query->resolution?->references[0]->declaration);
        self::assertSame([$users, $orders], $query->resolution->dependencies);
    }

    public function testAnalyzeAllPassesPartialDeclarationsToEveryStatement(): void
    {
        $statements = (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->analyzeAll('SELECT * FROM audit; DROP TABLE audit', [], \SqlSemantics\Core\Declarations::Partial);
        self::assertSame(\SqlSemantics\Statement\ReferenceKind::Undeclared, $statements[0]->resolution?->references[0]->kind);
        self::assertSame(\SqlSemantics\Statement\ReferenceKind::Drop, $statements[1]->resolution?->references[0]->kind);
        self::assertNull($statements[1]->resolution->references[0]->declaration);
    }

    public function testSplitReportsSyntaxErrorsAsAnalysisErrors(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite), ['main']))->split('SELECT 1; SELECT FROM');
    }
}
