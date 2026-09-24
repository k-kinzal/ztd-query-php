<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Objects;

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
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectMemory;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\Laravel\BuilderCalls;
use SqlCatalog\Extension\Laravel\QueryState;
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

#[CoversClass(CallbackEffects::class)]
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
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Clauses::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Grammar::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\Predicates::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelSet::class)]
final class CallbackEffectsTest extends TestCase
{
    public function testApplyFollowsMutationsOfTheCallbackParameter(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $callback = new \PhpParser\Node\Expr\ArrowFunction(['params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('q'))], 'expr' => new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'limit', [new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\Int_(2))])]);
        $index = new ProgramIndex();
        $object = (new BuilderCalls($index))->allocate(BuilderCalls::QUERY, new QueryState());
        $expressions = (new Interpreter($index, (new LaravelExtension())->sinks(), modelProviders: [new LaravelExtension()]))->evaluatorFor();
        $result = $effects->apply($callback, [Domain::of($object)], new Environment(), new FunctionScope('query.php'), $expressions)->soleObject();
        self::assertNotNull($result);
        self::assertSame(2, QueryState::from($result)->get('limit')->soleLiteral()?->value);
        self::assertSame($object->identity, $result->identity);
    }

    public function testApplyLeavesCallbacksWithoutAReceiverOpen(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $result = $effects->apply(new \PhpParser\Node\Expr\Closure(), [], new Environment(), new FunctionScope('query.php'), (new Interpreter(new ProgramIndex(), []))->evaluatorFor());
        self::assertFalse($result->isExact());
    }

    public function testBindKeepsOnlyExplicitClosureCapturesAndPassedArguments(): void
    {

        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects());
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));

        $callback = new \PhpParser\Node\Expr\Closure(['params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('q'))], 'uses' => [new \PhpParser\Node\Expr\ClosureUse(new \PhpParser\Node\Expr\Variable('id'))]]);
        $outer = new Environment(['id' => Domain::literal(7), 'secret' => Domain::literal('other')]);
        $bound = $effects->bind($callback, [Domain::literal('receiver')], $outer);
        self::assertSame(7, $bound->read('id')->soleLiteral()?->value);
        self::assertSame('receiver', $bound->read('q')->soleLiteral()?->value);
        self::assertFalse($bound->has('secret'));
        self::assertTrue($outer->has('secret'));
    }

    public function testSupportedRejectsEarlyReturnsAndReplacementQueries(): void
    {
        $budget = new EvaluationBudget();
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget), new SliceExecutor());
        $parameter = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('q'));
        $returned = new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\Variable('q'));
        self::assertTrue($effects->supported(new \PhpParser\Node\Expr\Closure(['params' => [$parameter], 'stmts' => [$returned]])));
        self::assertFalse($effects->supported(new \PhpParser\Node\Expr\Closure(['params' => [$parameter], 'stmts' => [$returned, new \PhpParser\Node\Stmt\Nop()]])));
        self::assertFalse($effects->supported(new \PhpParser\Node\Expr\Closure(['params' => [$parameter], 'stmts' => [new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\Variable('other'))]])));
    }

    public function testApplyRunsClosureEffectsThroughCapturedAliasesOfItsReceiver(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php $callback = function ($q) use ($alias, $id) { $alias->where("id", $id); };');
        $callback = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\Closure::class)[0];
        $budget = new EvaluationBudget();
        $names = new FreeNames();
        $modified = new ModifiedNames($names, new ObjectEffects([$file]));
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([$file]), $budget, $names, $modified), new SliceExecutor(modified: $modified, budget: $budget));
        $index = new ProgramIndex();
        $object = (new BuilderCalls($index))->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite')]));
        $outer = new Environment(['alias' => Domain::of($object), 'id' => Domain::literal(7)]);
        $result = $effects->apply($callback, [Domain::of($object)], $outer, new FunctionScope('query.php'), (new Interpreter($index, (new LaravelExtension())->sinks(), modelProviders: [new LaravelExtension()]))->evaluatorFor([$file]))->soleObject();
        self::assertNotNull($result);
        self::assertSame('"id" = ?', QueryState::from($result)->items('where')[0]->soleLiteral()?->value);
        self::assertSame(7, QueryState::from($result)->items('whereBindings')[0]->soleLiteral()?->value);
        self::assertSame($object, $outer->read('alias')->soleObject());
    }

    public function testSupportedAcceptsExplicitNullAndVoidReturns(): void
    {
        $budget = new EvaluationBudget();
        $effects = new CallbackEffects(new BackwardSlicer(new SourceTree([]), $budget), new SliceExecutor());
        $parameter = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('q'));
        self::assertTrue($effects->supported(new \PhpParser\Node\Expr\Closure(['params' => [$parameter], 'stmts' => [new \PhpParser\Node\Stmt\Return_()]])));
        self::assertTrue($effects->supported(new \PhpParser\Node\Expr\Closure(['params' => [$parameter], 'stmts' => [new \PhpParser\Node\Stmt\Return_(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('NULL')))]])));
        self::assertFalse($effects->supported(new \PhpParser\Node\Expr\Closure()));
    }
}
