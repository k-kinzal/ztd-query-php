<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Analysis\Derivation\SourceTree;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\Laravel\BuilderCalls;
use SqlCatalog\Analysis\Laravel\CallbackModel;
use SqlCatalog\Analysis\Laravel\Grammar;
use SqlCatalog\Analysis\Laravel\Predicates;
use SqlCatalog\Analysis\Laravel\QueryState;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectMemory;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\LaravelExtension;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

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
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
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
        $model = new CallbackModel($index, $effects);
        $object = (new BuilderCalls($index))->allocate('Illuminate\Database\Eloquent\Builder', new QueryState(['model' => Domain::literal('User'), 'dialect' => Domain::literal('sqlite')]));
        $call = new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'active');
        $result = $model->apply($call, $object, 'active', [], new Environment(), new FunctionScope('query.php'), (new Interpreter($index, (new LaravelExtension())->sinks()))->evaluatorFor([$file]))?->soleObject();
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
        $object = (new BuilderCalls($index))->allocate(BuilderCalls::QUERY, $state);
        $result = (new CallbackModel($index, $effects))->nested($callback, $object, 'orwhere', new Environment(), new FunctionScope('query.php'), (new Interpreter($index, (new LaravelExtension())->sinks()))->evaluatorFor())->soleObject();
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
        $model = new CallbackModel(new ProgramIndex(), $effects);
        $result = $model->checked(Domain::fromTerms([$object], true), $object)->soleObject();
        self::assertNotNull($result);
        self::assertFalse(QueryState::from($result)->get('problem')->isExact());
        self::assertSame($object, $model->checked(Domain::of($object), $object)->soleObject());
    }
}
