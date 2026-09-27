<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Analyzer;
use SqlSemantics\Core\AnalysisException;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\ResolutionMode;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Statement;
use Tests\Contract\FixtureCorpus;
use Tests\Contract\Resolved;

#[CoversClass(Analyzer::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(AnalysisException::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(Statement::class)]
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
        $this->expectException(AnalysisException::class);
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
        $this->expectException(AnalysisException::class);
        (new Analyzer(new Language(SqliteDialect::Sqlite)))->split('SELECT 1; SELECT FROM');
    }

    /**
     * @return iterable<string, array{Dialect, string, list<array{name: string, columns: list<string>}>}>
     */
    public static function providerFixtureDeclarations(): iterable
    {
        foreach (FixtureCorpus::cases() as $name => [$dialect, $sql, $error, $tables]) {
            if ($error === null) {
                yield $name => [$dialect, $sql, $tables];
            }
        }
    }

    /**
     * @return iterable<string, array{Dialect, string, class-string<AnalysisException|SemanticException>}>
     */
    public static function providerFixtureRejections(): iterable
    {
        foreach (FixtureCorpus::cases() as $name => [$dialect, $sql, $error]) {
            if ($error !== null) {
                yield $name => [$dialect, $sql, $error];
            }
        }
    }

    /**
     * @param list<array{name: string, columns: list<string>}> $expected
     */
    #[DataProvider('providerFixtureDeclarations')]
    public function testAnalyzeAllPreservesTheFixtureCorpus(Dialect $dialect, string $sql, array $expected): void
    {
        $analyzer = new Analyzer(new Language($dialect));
        $statements = $analyzer->analyzeAll($sql, resolutionMode: ResolutionMode::Partial);
        $tables = array_merge(...array_map(static fn (Statement $statement): array => Resolved::of($statement)->declarations, $statements));
        self::assertSame($expected, array_map(static fn (TableDefinition $table): array => ['name' => $table->name, 'columns' => array_column($table->columns, 'name')], $tables));
        $rendered = array_map(static fn (Statement $statement): string => $statement->toString(), $statements);
        self::assertNotEmpty($rendered);
        self::assertSame($rendered, array_map(static fn (Statement $statement): string => $statement->toString(), $analyzer->analyzeAll(implode(' ', $rendered))));
        self::assertStringNotContainsString('SqlParser\\', serialize($statements));
    }

    /**
     * @param class-string<AnalysisException|SemanticException> $error
     */
    #[DataProvider('providerFixtureRejections')]
    public function testAnalyzeAllRejectsInvalidFixtureDeclarations(Dialect $dialect, string $sql, string $error): void
    {
        $this->expectException($error);
        (new Analyzer(new Language($dialect)))->analyzeAll($sql, resolutionMode: ResolutionMode::Partial);
    }
}
