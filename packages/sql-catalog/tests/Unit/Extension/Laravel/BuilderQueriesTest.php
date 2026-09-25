<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Analysis\Derivation\Solution;
use SqlCatalog\Analysis\Derivation\SourceTree;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\SinkFinder;
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
use SqlCatalog\Extension\Laravel\BuilderQueries;
use SqlCatalog\Extension\Laravel\Clauses;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Extension\Laravel\SelectCompiler;
use SqlCatalog\Extension\Laravel\WriteCompiler;
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

#[CoversClass(BuilderQueries::class)]
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
#[UsesClass(Solution::class)]
#[UsesClass(SinkFinder::class)]
#[UsesClass(SelectCompiler::class)]
#[UsesClass(WriteCompiler::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Predicates::class)]
#[UsesClass(Clauses::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class BuilderQueriesTest extends TestCase
{
    public function testStatementsCompilesReceiverAndBindingsDerivedFromTheSameBranch(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $q->where("id", 7)->get();');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $deriver = (new Interpreter($index, (new LaravelExtension())->sinks(), dialect: 'sqlite', modelProviders: [new LaravelExtension()]))->deriverFor([$file]);
        $solutions = (new \SqlCatalog\Analysis\Model\ModelQueries())->solve($calls[0], new BuilderQueries($index), $deriver);
        self::assertCount(1, $solutions);
        self::assertSame('select * from "users" where "id" = ?', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertSame(7, $solutions[0]->values[1]->soleArray()?->positional()[0]->soleLiteral()?->value);
    }

    public function testCompileAppliesSoftDeletesOnlyAtExecution(): void
    {
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'softDeletes' => Domain::literal(true), 'deletedColumn' => Domain::literal('deleted_at')]);
        $queries = new BuilderQueries(new ProgramIndex());
        self::assertSame('select * from "users" where "users"."deleted_at" is null', $queries->compile($state, 'get', [])[0]->soleLiteral()?->value);
        self::assertSame('select * from "users"', $queries->compile($state->with('trashed', Domain::literal('withtrashed')), 'get', [])[0]->soleLiteral()?->value);
        self::assertSame('select * from "users" where "users"."deleted_at" is not null', $queries->compile($state->with('trashed', Domain::literal('onlytrashed')), 'get', [])[0]->soleLiteral()?->value);
        self::assertFalse($queries->compile($state, 'delete', [])[0]->isExact());
        self::assertFalse($queries->compile($state->with('timestamps', Domain::literal(true)), 'update', [])[0]->isExact());
    }

    public function testCompileKeepsUnknownEffectsAndMissingDialectsOpen(): void
    {
        $queries = new BuilderQueries(new ProgramIndex());
        self::assertFalse($queries->compile(new QueryState(['table' => Domain::literal('users')]), 'get', [])[0]->isExact());
        self::assertFalse($queries->compile((new QueryState())->reject('macro'), 'get', [])[0]->isExact());
    }

    public function testCompileGroupsDisjunctionsBeforeApplyingSoftDeletes(): void
    {
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'softDeletes' => Domain::literal(true), 'deletedColumn' => Domain::literal('deleted_at'), 'where' => QueryState::list([Domain::literal('"a" = ?'), Domain::literal('or "b" = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1), Domain::literal(2)])]);
        [$sql, $bindings] = (new BuilderQueries(new ProgramIndex()))->compile($state, 'get', []);
        self::assertSame('select * from "users" where ("a" = ? or "b" = ?) and "users"."deleted_at" is null', $sql->soleLiteral()?->value);
        self::assertCount(2, $bindings->soleArray()->entries ?? []);
    }
    public function testCompileRequiresADialectEvenWhenEveryIdentifierIsRaw(): void
    {
        $raw = Domain::of(new ObjectTerm('Illuminate\Database\Query\Expression', state: (new QueryState(['sql' => Domain::literal('users')]))->array()));
        $state = new QueryState(['table' => $raw, 'columns' => QueryState::list([Domain::literal('*')]), 'offset' => Domain::literal(1)]);
        self::assertFalse((new BuilderQueries(new ProgramIndex()))->compile($state, 'get', [])[0]->isExact());
    }

    public function testInputsUsesTheReceiverOrModelFactoryBeforeTheArguments(): void
    {
        $queries = new BuilderQueries(new ProgramIndex());
        $receiver = new \PhpParser\Node\Expr\Variable('builder');
        $argument = new \PhpParser\Node\Scalar\Int_(7);
        self::assertSame([$receiver, $argument], $queries->inputs(new \PhpParser\Node\Expr\MethodCall($receiver, 'find', [new \PhpParser\Node\Arg($argument)])));
        $inputs = $queries->inputs(new \PhpParser\Node\Expr\StaticCall(new \PhpParser\Node\Name('User'), 'get'));
        self::assertSame('User::query()', (new \SqlCatalog\Php\NodeText())->render($inputs[0]));
        self::assertSame([], $queries->inputs(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('other'))));
    }

}
