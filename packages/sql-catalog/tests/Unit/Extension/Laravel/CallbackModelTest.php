<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\FreeNames;
use SqlCatalog\Core\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Core\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectMemory;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Extension\Laravel\BuilderCalls;
use SqlCatalog\Extension\Laravel\CallbackModel;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\LaravelExtension;
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;

#[CoversClass(CallbackModel::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(ObjectEffects::class)]
#[UsesClass(CallbackEffects::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Environment::class)]
#[UsesClass(ObjectMemory::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(BuilderCalls::class)]
#[UsesClass(QueryState::class)]
#[UsesClass(LaravelExtension::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Predicates::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
final class CallbackModelTest extends TestCase
{
    public function testApplyRunsSourceDeclaredLocalScopes(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $file = (new SourceParser())->parse('model.php', '<?php class User extends \\Illuminate\\Database\\Eloquent\\Model { public function scopeActive($q) { return $q->where("active", 1); } }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $model = new CallbackModel($index, $effects, dialects: \SqlCatalog\Facade\Builtins::dialects());
        $object = (new BuilderCalls($index, dialects: \SqlCatalog\Facade\Builtins::dialects()))->allocate('Illuminate\Database\Eloquent\Builder', new QueryState(['model' => Domain::literal('User'), 'dialect' => Domain::literal('sqlite')]));
        $call = new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'active');
        $result = $model->apply($call, $object, 'active', [], new Environment(), new FunctionScope('query.php'), (new Interpreter($index, (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks(), modelProviders: [new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects())]))->evaluatorFor([$file]))?->soleObject();
        self::assertNotNull($result);
        self::assertSame('"active" = ?', QueryState::from($result)->items('where')[0]->soleLiteral()?->value);
        self::assertNull($model->apply($call, $object, 'missing', [], new Environment(), new FunctionScope('query.php'), (new Interpreter($index, []))->evaluatorFor()));
    }

    public function testNestedRestoresOuterClausesAndGroupsOnlyNewPredicates(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $callback = new \PhpParser\Node\Expr\ArrowFunction(['params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('q'))], 'expr' => new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'where', [new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_('id')), new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\Int_(7))])]);
        $index = new ProgramIndex();
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'limit' => Domain::literal(10), 'where' => QueryState::list([Domain::literal('active = 1')])]);
        $object = (new BuilderCalls($index, dialects: \SqlCatalog\Facade\Builtins::dialects()))->allocate(BuilderCalls::QUERY, $state);
        $result = (new CallbackModel($index, $effects, dialects: \SqlCatalog\Facade\Builtins::dialects()))->nested($callback, $object, 'orwhere', new Environment(), new FunctionScope('query.php'), (new Interpreter($index, (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks(), modelProviders: [new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects())]))->evaluatorFor())->soleObject();
        self::assertNotNull($result);
        $after = QueryState::from($result);
        self::assertSame('or ("id" = ?)', $after->items('where')[1]->soleLiteral()?->value);
        self::assertSame(10, $after->get('limit')->soleLiteral()?->value);
        self::assertSame($object->identity, $result->identity);
    }

    public function testCheckedPreservesAVisibleGapWhenTheCallbackExceedsItsBudget(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $object = new ObjectTerm(BuilderCalls::QUERY, identity: 'a', state: new ArrayTerm([]));
        $model = new CallbackModel(new ProgramIndex(), $effects, dialects: \SqlCatalog\Facade\Builtins::dialects());
        $result = $model->checked(Domain::fromTerms([$object], true), $object)->soleObject();
        self::assertNotNull($result);
        self::assertFalse(QueryState::from($result)->get('problem')->isExact());
        self::assertSame($object, $model->checked(Domain::of($object), $object)->soleObject());
    }
}
