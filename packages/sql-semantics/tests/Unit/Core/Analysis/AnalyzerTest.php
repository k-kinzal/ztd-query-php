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
        $analyzer = new Analyzer(new Language(SqliteDialect::Sqlite));
        $statement = $analyzer->analyze('DROP TABLE no_such_table');
        self::assertSame('DROP TABLE no_such_table', $statement->toString());
        self::assertNotSame($statement, $analyzer->analyze('DROP TABLE no_such_table'));
    }

    public function testAnalyzeRejectsInvalidSqlWithoutAnIncompleteStatement(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite)))->analyze('SELECT FROM');
    }

    public function testAnalyzeAllGivesOneStatementPerScriptStatement(): void
    {
        $statements = (new Analyzer(new Language(SqliteDialect::Sqlite)))->analyzeAll("SELECT 1; SELECT ';' -- done");
        self::assertCount(2, $statements);
        self::assertSame('SELECT 1 ;', $statements[0]->toString());
        self::assertSame("SELECT ';' -- done", $statements[1]->toString());
    }

    public function testSplitReportsSyntaxErrorsAsAnalysisErrors(): void
    {
        $this->expectException(\SqlSemantics\Core\AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite)))->split('SELECT 1; SELECT FROM');
    }
}
