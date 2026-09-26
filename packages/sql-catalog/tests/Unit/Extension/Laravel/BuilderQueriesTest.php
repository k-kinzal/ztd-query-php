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
use SqlCatalog\Core\Analysis\Derivation\Solution;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Analysis\SinkFinder;
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
use SqlCatalog\Extension\Laravel\BuilderQueries;
use SqlCatalog\Extension\Laravel\Clauses;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\LaravelExtension;
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Extension\Laravel\SelectCompiler;
use SqlCatalog\Extension\Laravel\WriteCompiler;

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
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
final class BuilderQueriesTest extends TestCase
{
    public function testStatementsCompilesReceiverAndBindingsDerivedFromTheSameBranch(): void
    {
        $file = (new SourceParser())->parse('query.php', '<?php use Illuminate\\Support\\Facades\\DB; $q = DB::table("users"); $q->where("id", 7)->get();');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $calls = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\MethodCall::class);
        $deriver = (new Interpreter($index, (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks(), dialect: 'sqlite', modelProviders: [new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects())]))->deriverFor([$file]);
        $solutions = (new \SqlCatalog\Core\Analysis\Model\ModelQueries())->solve($calls[0], new BuilderQueries($index, dialects: \SqlCatalog\Facade\Builtins::dialects()), $deriver);
        self::assertCount(1, $solutions);
        self::assertSame('select * from "users" where "id" = ?', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertSame(7, $solutions[0]->values[1]->soleArray()?->positional()[0]->soleLiteral()?->value);
    }

    public function testCompileAppliesSoftDeletesOnlyAtExecution(): void
    {
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'softDeletes' => Domain::literal(true), 'deletedColumn' => Domain::literal('deleted_at')]);
        $queries = new BuilderQueries(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertSame('select * from "users" where "users"."deleted_at" is null', $queries->compile($state, 'get', [])[0]->soleLiteral()?->value);
        self::assertSame('select * from "users"', $queries->compile($state->with('trashed', Domain::literal('withtrashed')), 'get', [])[0]->soleLiteral()?->value);
        self::assertSame('select * from "users" where "users"."deleted_at" is not null', $queries->compile($state->with('trashed', Domain::literal('onlytrashed')), 'get', [])[0]->soleLiteral()?->value);
        self::assertFalse($queries->compile($state, 'delete', [])[0]->isExact());
        self::assertFalse($queries->compile($state->with('timestamps', Domain::literal(true)), 'update', [])[0]->isExact());
    }

    public function testCompileKeepsUnknownEffectsAndMissingDialectsOpen(): void
    {
        $queries = new BuilderQueries(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertFalse($queries->compile(new QueryState(['table' => Domain::literal('users')]), 'get', [])[0]->isExact());
        self::assertFalse($queries->compile((new QueryState())->reject('macro'), 'get', [])[0]->isExact());
    }

    public function testCompileGroupsDisjunctionsBeforeApplyingSoftDeletes(): void
    {
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'softDeletes' => Domain::literal(true), 'deletedColumn' => Domain::literal('deleted_at'), 'where' => QueryState::list([Domain::literal('"a" = ?'), Domain::literal('or "b" = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1), Domain::literal(2)])]);
        [$sql, $bindings] = (new BuilderQueries(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects()))->compile($state, 'get', []);
        self::assertSame('select * from "users" where ("a" = ? or "b" = ?) and "users"."deleted_at" is null', $sql->soleLiteral()?->value);
        self::assertCount(2, $bindings->soleArray()->entries ?? []);
    }
    public function testCompileRequiresADialectEvenWhenEveryIdentifierIsRaw(): void
    {
        $raw = Domain::of(new ObjectTerm('Illuminate\Database\Query\Expression', state: (new QueryState(['sql' => Domain::literal('users')]))->array()));
        $state = new QueryState(['table' => $raw, 'columns' => QueryState::list([Domain::literal('*')]), 'offset' => Domain::literal(1)]);
        self::assertFalse((new BuilderQueries(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects()))->compile($state, 'get', [])[0]->isExact());
    }

    public function testInputsUsesTheReceiverOrModelFactoryBeforeTheArguments(): void
    {
        $queries = new BuilderQueries(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $receiver = new \PhpParser\Node\Expr\Variable('builder');
        $argument = new \PhpParser\Node\Scalar\Int_(7);
        self::assertSame([$receiver, $argument], $queries->inputs(new \PhpParser\Node\Expr\MethodCall($receiver, 'find', [new \PhpParser\Node\Arg($argument)])));
        $inputs = $queries->inputs(new \PhpParser\Node\Expr\StaticCall(new \PhpParser\Node\Name('User'), 'get'));
        self::assertSame('User::query()', (new \SqlCatalog\Core\Php\NodeText())->render($inputs[0]));
        self::assertSame([], $queries->inputs(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('other'))));
    }


    public function testOutputsListsEveryStatementAPaginatedOrEagerReadIssues(): void
    {
        $queries = new BuilderQueries(new ProgramIndex(), \SqlCatalog\Facade\Builtins::dialects());
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'where' => QueryState::list([Domain::literal('"a" = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1)])]);
        $outputs = $queries->outputs($state, 'paginate', [Domain::literal(10)]);
        self::assertCount(2, $outputs);
        self::assertSame('select count(*) as "aggregate" from "users" where "a" = ?', $outputs[0][0]->soleLiteral()?->value);
        self::assertSame('select * from "users" where "a" = ? limit 10 offset {$}', $outputs[1][0]->patterns()[0]->display());
        $eager = $queries->outputs($state->with('eager', Domain::literal(true)), 'get', []);
        self::assertCount(2, $eager);
        self::assertSame('select * from "users" where "a" = ?', $eager[0][0]->soleLiteral()?->value);
        self::assertFalse($eager[1][0]->isExact());
        self::assertCount(1, $queries->outputs($state->with('eager', Domain::literal(true)), 'delete', []));
        $problem = $queries->outputs($state->reject('macro'), 'paginate', []);
        self::assertCount(1, $problem);
        self::assertFalse($problem[0][0]->isExact());
    }

    public function testPrepareAppliesScopesAndMarksUnmodelledWrites(): void
    {
        $queries = new BuilderQueries(new ProgramIndex(), \SqlCatalog\Facade\Builtins::dialects());
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users'), 'softDeletes' => Domain::literal(true), 'deletedColumn' => Domain::literal('deleted_at'), 'timestamps' => Domain::literal(true)]);
        self::assertSame('"users"."deleted_at" is null', $queries->prepare($state, 'get')->items('where')[0]->soleLiteral()?->value);
        self::assertArrayNotHasKey('problem', $queries->prepare($state, 'get')->fields);
        self::assertFalse($queries->prepare($state, 'increment')->get('problem')->isExact());
        self::assertFalse($queries->prepare($state, 'delete')->get('problem')->isExact());
        self::assertFalse($queries->prepare(new QueryState(['table' => Domain::literal('users')]), 'get')->get('problem')->isExact());
    }

    public function testDispatchRoutesReadsAndWritesToTheirCompilers(): void
    {
        $queries = new BuilderQueries(new ProgramIndex(), \SqlCatalog\Facade\Builtins::dialects());
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        $state = new QueryState(['dialect' => Domain::literal('sqlite'), 'table' => Domain::literal('users')]);
        self::assertSame('select * from "users"', $queries->dispatch($state, 'cursor', [], $grammar)[0]->soleLiteral()?->value);
        self::assertSame('delete from "users"', $queries->dispatch($state, 'delete', [], $grammar)[0]->soleLiteral()?->value);
    }
}
