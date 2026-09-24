<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\Laravel\BuilderCalls;
use SqlCatalog\Analysis\Laravel\Clauses;
use SqlCatalog\Analysis\Laravel\Grammar;
use SqlCatalog\Analysis\Laravel\ModelMetadata;
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
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(BuilderCalls::class)]
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
#[UsesClass(QueryState::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Clauses::class)]
#[UsesClass(Predicates::class)]
#[UsesClass(ModelMetadata::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(Environment::class)]
#[UsesClass(ObjectMemory::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
final class BuilderCallsTest extends TestCase
{
    public function testIsBuilderRecognizesSourceDeclaredModelsAndBothBuilderContracts(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        self::assertTrue($calls->isBuilder(BuilderCalls::QUERY));
        self::assertTrue($calls->isBuilder(ModelMetadata::BUILDER));
        self::assertTrue($calls->isBuilder(ModelMetadata::MODEL));
        self::assertFalse($calls->isBuilder('Collection'));
    }

    public function testStaticCallCreatesFacadeQueriesAndDoesNotModelUnrelatedStatics(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), 'sqlite');
        $env = new Environment();
        $value = $calls->staticCall(BuilderCalls::FACADE, 'table', [Domain::literal('users')], $env);
        self::assertNotNull($value?->soleObject());
        self::assertNull($calls->staticCall('Other', 'table', [], $env));
        $query = $calls->staticCall(ModelMetadata::MODEL, 'query', [], $env)?->soleObject();
        self::assertSame(ModelMetadata::BUILDER, $query?->className);
    }

    public function testMethodCallMutatesTheExistingIdentityAndLeavesOtherClassesAlone(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $env = new Environment();
        $query = $calls->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite')]));
        $result = $calls->methodCall(Domain::of($query), 'where', [Domain::literal('id'), Domain::literal(1)], $env)?->soleObject();
        self::assertSame($query->identity, $result?->identity);
        self::assertNotNull($result);
        self::assertSame('"id" = ?', QueryState::from($result)->items('where')[0]->soleLiteral()?->value);
        self::assertNull($calls->methodCall(Domain::of(new ObjectTerm('Collection')), 'where', [], $env));
        $connection = Domain::of(new ObjectTerm('Illuminate\Database\SQLiteConnection'));
        self::assertSame(BuilderCalls::QUERY, $calls->methodCall($connection, 'table', [Domain::literal('users')], $env)?->soleObject()?->className);
    }

    public function testCallbackReturnsNullWithoutARegisteredRunner(): void
    {
        $index = new ProgramIndex();
        $calls = new BuilderCalls($index);
        self::assertNull($calls->callback(new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'where'), new ObjectTerm(BuilderCalls::QUERY), 'where', [], new Environment(), new FunctionScope('test.php'), (new Interpreter($index, []))->evaluatorFor()));
    }

    public function testIsConnectionRecognizesConcreteGrammarContracts(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        self::assertTrue($calls->isConnection('Illuminate\Database\MySqlConnection'));
        self::assertTrue($calls->isConnection(BuilderCalls::CONNECTION));
        self::assertFalse($calls->isConnection('Unknown'));
    }

    public function testConnectionDialectUsesConcreteConnectionsBeforeTheFallback(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), 'mysql');
        self::assertSame('sqlite', $calls->connectionDialect('Illuminate\Database\SQLiteConnection'));
        self::assertSame('pgsql', $calls->connectionDialect('Illuminate\Database\PostgresConnection'));
        self::assertSame('mysql', $calls->connectionDialect(BuilderCalls::CONNECTION));
        self::assertNull((new BuilderCalls(new ProgramIndex()))->connectionDialect(null));
    }

    public function testConnectionCallAllocatesIndependentQueriesAndExplicitRawExpressions(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $state = new QueryState(['dialect' => Domain::literal('pgsql')]);
        $env = new Environment();
        $a = $calls->connectionCall('table', [Domain::literal('users')], $state, $env)?->soleObject();
        $b = $calls->connectionCall('table', [Domain::literal('users')], $state, $env)?->soleObject();
        self::assertNotSame($a?->identity, $b?->identity);
        self::assertSame('Illuminate\Database\Query\Expression', $calls->connectionCall('raw', [Domain::literal('now()')], $state, $env)?->soleObject()?->className);
        self::assertSame(BuilderCalls::CONNECTION, $calls->connectionCall('connection', [], $state, $env)?->soleObject()?->className);
        self::assertNull($calls->connectionCall('table', [], $state, $env));
    }

    public function testAllocateNeverReusesAnIdentityForTheSameState(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        self::assertNotSame($calls->allocate(BuilderCalls::QUERY, new QueryState())->identity, $calls->allocate(BuilderCalls::QUERY, new QueryState())->identity);
    }

    public function testMutateRetainsEarlierUnknownEffects(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite')]));
        $env = new Environment();
        $unknown = $calls->mutate($object, 'macro', [], $env)->soleObject();
        self::assertNotNull($unknown);
        $updated = $calls->mutate($unknown, 'limit', [Domain::literal(3)], $env)->soleObject();
        self::assertNotNull($updated);
        self::assertFalse(QueryState::from($updated)->get('problem')->isExact());
        self::assertSame(3, QueryState::from($updated)->get('limit')->soleLiteral()?->value);
    }

    public function testPositionalRejectsNamedAndUnpackedArguments(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $value = new \PhpParser\Node\Scalar\String_('id');
        self::assertTrue($calls->positional([new \PhpParser\Node\Arg($value)]));
        self::assertFalse($calls->positional([new \PhpParser\Node\Arg($value, name: new \PhpParser\Node\Identifier('column'))]));
        self::assertFalse($calls->positional([new \PhpParser\Node\Arg($value, unpack: true)]));
    }

    public function testUnsupportedInvalidatesAliasesWithoutLosingTheirIdentity(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(BuilderCalls::QUERY, new QueryState());
        $env = new Environment(['alias' => Domain::of($object)]);
        $calls->unsupported(Domain::of($object), $env, 'unknown mutation');
        $read = $env->read('alias')->soleObject();
        self::assertNotNull($read);
        self::assertSame($object->identity, $read->identity);
        self::assertFalse(QueryState::from($read)->get('problem')->isExact());
    }

    public function testExecutionPreservesFirstLimitAndReturnsACollectionForGet(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(BuilderCalls::QUERY, new QueryState());
        $env = new Environment(['q' => Domain::of($object)]);
        $calls->execution(Domain::of($object), 'first', [], $env);
        $after = $env->read('q')->soleObject();
        self::assertNotNull($after);
        self::assertSame(1, QueryState::from($after)->get('limit')->soleLiteral()?->value);
        self::assertSame('Illuminate\Support\Collection', $calls->execution(Domain::of($after), 'get', [], $env)->type()->soleClassName());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerExecutionTypes')]
    public function testExecutionReturnsTheFrameworkTypeWithoutInventingAnotherQuery(string $method, string $type): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(BuilderCalls::QUERY, new QueryState(['key' => Domain::literal('id'), 'dialect' => Domain::literal('sqlite')]));
        $result = $calls->execution(Domain::of($object), $method, [Domain::literal(7)], new Environment());
        self::assertSame([$type], $result->type()->names);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function providerExecutionTypes(): iterable
    {
        foreach (['get', 'all', 'pluck'] as $method) {
            yield [$method, 'Illuminate\Support\Collection'];
        }
        foreach (['first', 'firstorfail', 'find'] as $method) {
            yield [$method, 'stdClass'];
        }
        foreach (['exists', 'doesntexist', 'insert', 'insertorignore'] as $method) {
            yield [$method, 'bool'];
        }
        foreach (['count', 'update', 'delete', 'insertgetid'] as $method) {
            yield [$method, 'int'];
        }
    }

    public function testExecutionFindRetainsItsPredicateAndTheModelReturnType(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(ModelMetadata::BUILDER, new QueryState(['key' => Domain::literal('users.id'), 'model' => Domain::literal('App\User'), 'dialect' => Domain::literal('sqlite')]));
        $env = new Environment(['q' => Domain::of($object)]);
        $result = $calls->execution(Domain::of($object), 'find', [Domain::literal(7)], $env);
        self::assertSame('App\User', $result->type()->soleClassName());
        $after = $env->read('q')->soleObject();
        self::assertNotNull($after);
        self::assertSame('"users"."id" = ?', QueryState::from($after)->items('where')[0]->soleLiteral()?->value);
        self::assertSame(7, QueryState::from($after)->items('whereBindings')[0]->soleLiteral()?->value);
    }

    public function testMutateOnlyChangesSoftDeleteModesForModelsUsingTheTrait(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(ModelMetadata::BUILDER, new QueryState(['softDeletes' => Domain::literal(true)]));
        $env = new Environment();
        $with = $calls->mutate($object, 'withtrashed', [], $env)->soleObject();
        $only = $calls->mutate($object, 'onlytrashed', [], $env)->soleObject();
        self::assertNotNull($with);
        self::assertNotNull($only);
        self::assertSame('withtrashed', QueryState::from($with)->string('trashed'));
        self::assertSame('onlytrashed', QueryState::from($only)->string('trashed'));
        $plain = $calls->allocate(ModelMetadata::BUILDER, new QueryState());
        $unsupported = $calls->mutate($plain, 'withtrashed', [], $env)->soleObject();
        self::assertNotNull($unsupported);
        self::assertFalse(QueryState::from($unsupported)->get('problem')->isExact());
    }

    public function testConnectionCallPreservesRawSqlAndAnExplicitConnectionName(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), 'mysql');
        $env = new Environment();
        $raw = $calls->connectionCall('raw', [Domain::literal('count(*)')], new QueryState(), $env)?->soleObject();
        self::assertNotNull($raw);
        self::assertSame('count(*)', QueryState::from($raw)->string('sql'));
        self::assertNotNull($calls->connectionCall('connection', [Domain::literal('reporting')], new QueryState(), $env)?->soleObject());
        self::assertSame('mysql', $calls->connectionDialect('Illuminate\Database\MySqlConnection'));
    }

    public function testMutateNamesTheUnmodelledOperationInItsDiagnostic(): void
    {
        $calls = new BuilderCalls(new ProgramIndex());
        $object = $calls->allocate(BuilderCalls::QUERY, new QueryState());
        $updated = $calls->mutate($object, 'customFilter', [], new Environment())->soleObject();
        self::assertNotNull($updated);
        $problem = QueryState::from($updated)->get('problem')->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $problem);
        self::assertSame('Unmodelled Laravel effect: customFilter', $problem->expression);
        $result = $calls->execution(Domain::of($object), 'get', [], new Environment())->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $result);
        self::assertSame('Laravel get', $result->expression);
    }
}
