<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\JsonReporter;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(JsonReporter::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderRef::class)]
final class JsonReporterTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('json', (new JsonReporter())->name());
    }

    public function testDescriptionMentionsWhatItProduces(): void
    {
        self::assertStringContainsString('JSON', (new JsonReporter())->description());
    }

    public function testRenderWritesOneJsonFile(): void
    {
        $artifacts = (new JsonReporter())->render(new Catalog());
        self::assertSame([JsonReporter::FILE], $artifacts->names());
        self::assertStringEndsWith("\n", (string) $artifacts->sole());
    }

    public function testRenderIsTheSameForTheSameCatalog(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        $reporter = new JsonReporter();
        self::assertSame($reporter->render($catalog)->sole(), $reporter->render($catalog)->sole());
    }

    public function testRenderWritesTheWholeDocumentExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);
        $identifier = $catalog->entries()[0]->id;

        self::assertSame(
            '{' . "\n"
            . '    "version": 1,' . "\n"
            . '    "summary": {' . "\n"
            . '        "statements": 1,' . "\n"
            . '        "exact": 1,' . "\n"
            . '        "dynamic": 0,' . "\n"
            . '        "findings": 0' . "\n"
            . '    },' . "\n"
            . '    "statements": [' . "\n"
            . '        {' . "\n"
            . '            "id": "' . $identifier . '",' . "\n"
            . '            "kind": "select",' . "\n"
            . '            "sql": "SELECT id FROM users WHERE id = ?",' . "\n"
            . '            "exact": true,' . "\n"
            . '            "tables": [' . "\n"
            . '                "users"' . "\n"
            . '            ],' . "\n"
            . '            "site": {' . "\n"
            . '                "file": "a.php",' . "\n"
            . '                "line": 1,' . "\n"
            . '                "function": "f",' . "\n"
            . '                "sink": "pdo.prepare"' . "\n"
            . '            },' . "\n"
            . '            "placeholders": [' . "\n"
            . '                {' . "\n"
            . '                    "token": "?",' . "\n"
            . '                    "position": 0,' . "\n"
            . '                    "name": null,' . "\n"
            . '                    "value": {' . "\n"
            . '                        "type": "int",' . "\n"
            . '                        "values": [' . "\n"
            . '                            7' . "\n"
            . '                        ],' . "\n"
            . '                        "exhaustive": true,' . "\n"
            . '                        "origins": []' . "\n"
            . '                    }' . "\n"
            . '                }' . "\n"
            . '            ],' . "\n"
            . '            "findings": []' . "\n"
            . '        }' . "\n"
            . '    ],' . "\n"
            . '    "problems": []' . "\n"
            . '}' . "\n",
            (string) (new JsonReporter())->render($catalog)->sole(),
        );
    }

    public function testToArrayCarriesTheVersionAndTheStatements(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT id FROM users"); }',
        ]);
        $document = (new JsonReporter())->toArray($catalog);
        self::assertSame(JsonReporter::VERSION, $document['version']);
        self::assertSame('SELECT id FROM users', $document['statements'][0]['sql']);
        self::assertSame(['users'], $document['statements'][0]['tables']);
        self::assertSame('a.php', $document['statements'][0]['site']['file']);
    }

    public function testToArrayCarriesTheFilesThatCouldNotBeRead(): void
    {
        $document = (new JsonReporter())->toArray(new Catalog([], [new AnalysisProblem('a.php', 'broken')]));
        self::assertSame([['file' => 'a.php', 'message' => 'broken']], $document['problems']);
    }

    public function testSummaryCountsResolvedAndDynamicStatements(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, string $n) { $d->query("SELECT 1"); $d->query("SELECT " . $n); }',
        ]);
        $summary = (new JsonReporter())->summary($catalog);
        self::assertSame(2, $summary['statements']);
        self::assertSame(1, $summary['exact']);
        self::assertSame(1, $summary['dynamic']);
        self::assertGreaterThan(0, $summary['findings']);
    }

    public function testEntryToArrayCarriesTheFindings(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT " . $_GET["x"]); }',
        ]);
        $node = (new JsonReporter())->entryToArray($catalog->entries()[0]);
        self::assertFalse($node['exact']);
        self::assertNotSame([], $node['findings']);
        self::assertSame('high', $node['findings'][count($node['findings']) - 1]['severity']);
    }

    public function testPlaceholderToArrayCarriesTheBoundValue(): void
    {
        $placeholder = new Placeholder(':id', 0, 'id', new ValueDomain('int', [1], true, []));
        $node = (new JsonReporter())->placeholderToArray($placeholder);
        self::assertSame(':id', $node['token']);
        self::assertSame(['type' => 'int', 'values' => [1], 'exhaustive' => true, 'origins' => []], $node['value']);
    }

    public function testPlaceholderToArrayReportsAnUnboundParameter(): void
    {
        self::assertNull((new JsonReporter())->placeholderToArray(new Placeholder('?', 0, null, null))['value']);
    }
}
