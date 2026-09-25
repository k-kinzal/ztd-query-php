<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Json;

use JsonException;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;
use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Placeholder;
use SqlCatalog\Core\Catalog\ValueDomain;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Facade\AnalysisOptions;
use SqlCatalog\Facade\Analyzer;
use SqlCatalog\Reporter\Json\JsonReporter;

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
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Finding::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\FindingRule::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Pdo\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Core\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Core\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\Origin::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Php\SyntaxException::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
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

    /**
     * @throws JsonException
     */
    public function testTheDocumentMatchesTheSchemaItDeclares(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d, string $t): void {'
                . ' $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]);'
                . ' $d->query("SELECT " . $_GET["x"]); $d->query("SELECT * FROM " . $t); }',
            'broken.php' => '<?php function {',
        ]);
        $reporter = new JsonReporter();
        $document = json_decode((string) $reporter->render($catalog)->get(JsonReporter::FILE), false, 64, JSON_THROW_ON_ERROR);

        $result = (new Validator())->validate($document, $reporter->schema());

        self::assertNull($result->error());
    }

    public function testTheSchemaIsWrittenBesideTheDocument(): void
    {
        $artifacts = (new JsonReporter())->render(new Catalog());

        self::assertSame([JsonReporter::SCHEMA_FILE, JsonReporter::FILE], $artifacts->names());
        self::assertSame($artifacts->get(JsonReporter::FILE), $artifacts->primary());
    }

    /**
     * @throws JsonException
     */
    public function testSchemaIsTheShippedDocumentSchema(): void
    {
        $schema = json_decode((new JsonReporter())->schema(), true, 64, JSON_THROW_ON_ERROR);

        self::assertIsArray($schema);
        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
    }

    public function testRenderWritesTheJsonDocument(): void
    {
        $artifacts = (new JsonReporter())->render(new Catalog());

        self::assertStringEndsWith("\n", (string) $artifacts->get(JsonReporter::FILE));
    }

    public function testRenderIsTheSameForTheSameCatalog(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
        ]);
        $reporter = new JsonReporter();

        self::assertSame($reporter->render($catalog)->all(), $reporter->render($catalog)->all());
    }

    public function testRenderWritesTheWholeDocumentExactly(): void
    {
        $catalog = (new Analyzer())->analyzeSource([
            'a.php' => '<?php function f(PDO $d) { $s = $d->prepare("SELECT id FROM users WHERE id = ?"); $s->execute([7]); }',
        ]);
        $identifier = $catalog->entries()[0]->id;

        self::assertSame(
            '{' . "\n"
            . '    "$schema": "catalog-schema.json",' . "\n"
            . '    "version": 2,' . "\n"
            . '    "analysis": {' . "\n"
            . '        "conditions": "not-evaluated",' . "\n"
            . '        "reachability": "not-assessed"' . "\n"
            . '    },' . "\n"
            . '    "summary": {' . "\n"
            . '        "statements": 1,' . "\n"
            . '        "resolved": 1,' . "\n"
            . '        "undetermined": 0,' . "\n"
            . '        "findings": 0' . "\n"
            . '    },' . "\n"
            . '    "statements": [' . "\n"
            . '        {' . "\n"
            . '            "id": "' . $identifier . '",' . "\n"
            . '            "kind": "select",' . "\n"
            . '            "sql": "SELECT id FROM users WHERE id = ?",' . "\n"
            . '            "exact": true,' . "\n"
            . '            "resolution": "resolved",' . "\n"
            . '            "searchClosed": true,' . "\n"
            . '            "correlated": true,' . "\n"
            . '            "tables": [' . "\n"
            . '                "users"' . "\n"
            . '            ],' . "\n"
            . '            "site": {' . "\n"
            . '                "file": "a.php",' . "\n"
            . '                "line": 1,' . "\n"
            . '                "function": "f",' . "\n"
            . '                "sink": "pdo.prepare"' . "\n"
            . '            },' . "\n"
            . '            "through": [' . "\n"
            . '                "f"' . "\n"
            . '            ],' . "\n"
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
            (string) (new JsonReporter())->render($catalog)->get(JsonReporter::FILE),
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
        self::assertSame(1, $summary['resolved']);
        self::assertSame(1, $summary['undetermined']);
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
