<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Laravel\CallModel;

#[CoversClass(CallModel::class)]
#[UsesClass(\SqlCatalog\Facade\AnalysisOptions::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Objects\ObjectEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\StatementRecorder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Facade\Analyzer::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Catalog::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\CallResults::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderCalls::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Clauses::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Grammar::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Predicates::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\QueryState::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\SelectCompiler::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Pdo\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderRef::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Core\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Core\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
final class CallModelTest extends TestCase
{
    public function testEvaluateModelsFactoriesMutationsAndLeavesOtherCallsAlone(): void
    {
        $catalog = (new \SqlCatalog\Facade\Analyzer())->analyzeSource(['query.php' => '<?php use Illuminate\\Support\\Facades\\DB; function name() { return "users"; } DB::table(name())->where("id", 7)->get();'], new \SqlCatalog\Facade\AnalysisOptions(['laravel'], dialect: 'sqlite'));
        self::assertSame('select * from "users" where "id" = ?', $catalog->entries()[0]->sql());
        self::assertTrue($catalog->entries()[0]->searchClosed());
    }

    public function testMatchesClassRecognizesConcreteFrameworkTypesOnlyWhenEnabled(): void
    {
        $catalog = (new \SqlCatalog\Facade\Analyzer())->analyzeSource(['query.php' => '<?php function run(\\Illuminate\\Database\\MySqlConnection $db) { $db->table("users")->get(); }'], new \SqlCatalog\Facade\AnalysisOptions(['laravel']));
        self::assertSame('select * from `users`', $catalog->entries()[0]->sql());
    }
}
