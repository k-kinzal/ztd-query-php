<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Laravel\CallModel;

#[CoversClass(CallModel::class)]
#[UsesClass(\SqlCatalog\AnalysisOptions::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Objects\CallbackEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Objects\ObjectEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Analyzer::class)]
#[UsesClass(\SqlCatalog\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Catalog\Catalog::class)]
#[UsesClass(\SqlCatalog\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderCalls::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Clauses::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Grammar::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Predicates::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\QueryState::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\SelectCompiler::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
final class CallModelTest extends TestCase
{
    public function testEvaluateModelsFactoriesMutationsAndLeavesOtherCallsAlone(): void
    {
        $catalog = (new \SqlCatalog\Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function name() { return "users"; } DB::table(name())->where("id", 7)->get();'], new \SqlCatalog\AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertSame('select * from "users" where "id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testMatchesClassRecognizesConcreteFrameworkTypesOnlyWhenEnabled(): void
    {
        $catalog = (new \SqlCatalog\Analyzer())->analyzeSource(['query.php' => '<?php function run(\\Illuminate\\Database\\MySqlConnection $db) { $db->table("users")->get(); }'], new \SqlCatalog\AnalysisOptions(['laravel']));
        self::assertSame('select * from `users`', $catalog->entries()[0]->sql());
    }
}
