<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Extension\Laravel\BuilderCalls;
use SqlCatalog\Extension\Laravel\Clauses;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\ModelMetadata;
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;

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
#[UsesClass(\SqlCatalog\Extension\Laravel\SelectCompiler::class)]
#[UsesClass(Predicates::class)]
#[UsesClass(ModelMetadata::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(Environment::class)]
#[UsesClass(ObjectMemory::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
final class BuilderCallsTest extends TestCase
{
    public function testIsBuilderRecognizesSourceDeclaredModelsAndBothBuilderContracts(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertTrue($calls->isBuilder(BuilderCalls::QUERY));
        self::assertTrue($calls->isBuilder(ModelMetadata::BUILDER));
        self::assertTrue($calls->isBuilder(ModelMetadata::MODEL));
        self::assertFalse($calls->isBuilder('Collection'));
    }

    public function testStaticCallCreatesFacadeQueriesAndDoesNotModelUnrelatedStatics(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), 'sqlite', dialects: \SqlCatalog\Facade\Builtins::dialects());
        $env = new Environment();
        $value = $calls->staticCall(BuilderCalls::FACADE, 'table', [Domain::literal('users')], $env);
        self::assertNotNull($value?->soleObject());
        self::assertNull($calls->staticCall('Other', 'table', [], $env));
        $query = $calls->staticCall(ModelMetadata::MODEL, 'query', [], $env)?->soleObject();
        self::assertSame(ModelMetadata::BUILDER, $query?->className);
    }

    public function testMethodCallMutatesTheExistingIdentityAndLeavesOtherClassesAlone(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls($index, dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertNull($calls->callback(new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('q'), 'where'), new ObjectTerm(BuilderCalls::QUERY), 'where', [], new Environment(), new FunctionScope('test.php'), (new Interpreter($index, []))->evaluatorFor()));
    }

    public function testIsConnectionRecognizesConcreteGrammarContracts(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertTrue($calls->isConnection('Illuminate\Database\MySqlConnection'));
        self::assertTrue($calls->isConnection(BuilderCalls::CONNECTION));
        self::assertFalse($calls->isConnection('Unknown'));
    }

    public function testConnectionDialectUsesConcreteConnectionsBeforeTheFallback(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), 'mysql', dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertSame('sqlite', $calls->connectionDialect('Illuminate\Database\SQLiteConnection'));
        self::assertSame('pgsql', $calls->connectionDialect('Illuminate\Database\PostgresConnection'));
        self::assertSame('mysql', $calls->connectionDialect(BuilderCalls::CONNECTION));
        self::assertNull((new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects()))->connectionDialect(null));
    }

    public function testConnectionCallAllocatesIndependentQueriesAndExplicitRawExpressions(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertNotSame($calls->allocate(BuilderCalls::QUERY, new QueryState())->identity, $calls->allocate(BuilderCalls::QUERY, new QueryState())->identity);
    }

    public function testMutateRetainsEarlierUnknownEffects(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $value = new \PhpParser\Node\Scalar\String_('id');
        self::assertTrue($calls->positional([new \PhpParser\Node\Arg($value)]));
        self::assertFalse($calls->positional([new \PhpParser\Node\Arg($value, name: new \PhpParser\Node\Identifier('column'))]));
        self::assertFalse($calls->positional([new \PhpParser\Node\Arg($value, unpack: true)]));
    }

    public function testUnsupportedInvalidatesAliasesWithoutLosingTheirIdentity(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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
        $calls = new BuilderCalls(new ProgramIndex(), 'mysql', dialects: \SqlCatalog\Facade\Builtins::dialects());
        $env = new Environment();
        $raw = $calls->connectionCall('raw', [Domain::literal('count(*)')], new QueryState(), $env)?->soleObject();
        self::assertNotNull($raw);
        self::assertSame('count(*)', QueryState::from($raw)->string('sql'));
        self::assertNotNull($calls->connectionCall('connection', [Domain::literal('reporting')], new QueryState(), $env)?->soleObject());
        self::assertSame('mysql', $calls->connectionDialect('Illuminate\Database\MySqlConnection'));
    }

    public function testMutateNamesTheUnmodelledOperationInItsDiagnostic(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
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


    public function testBoundedMarksTooManyAlternativesAsOneOpenStateInsteadOfLosingEffects(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $terms = array_map(static fn (int $i): ObjectTerm => new ObjectTerm(BuilderCalls::QUERY, identity: 'a', state: (new QueryState(['limit' => Domain::literal($i)]))->array()), range(0, BuilderCalls::MAX_ALTERNATIVES));
        $bounded = $calls->bounded($terms, Domain::unknown());
        self::assertTrue($bounded->widened);
        self::assertNotNull($bounded->soleObject());
        self::assertFalse(QueryState::from($bounded->soleObject())->get('problem')->isExact());
        $kept = $calls->bounded(array_slice($terms, 0, BuilderCalls::MAX_ALTERNATIVES), Domain::unknown());
        self::assertCount(BuilderCalls::MAX_ALTERNATIVES, $kept->terms);
        self::assertFalse($kept->widened);
        self::assertCount(1, $calls->bounded(array_fill(0, BuilderCalls::MAX_ALTERNATIVES + 1, new LiteralTerm(1)), Domain::unknown())->terms);
    }

    public function testIsConnectionRecognizesInjectedConnectionContracts(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        self::assertTrue($calls->isConnection('Illuminate\\Database\\ConnectionInterface'));
        self::assertTrue($calls->isConnection('Illuminate\\Database\\DatabaseManager'));
        self::assertTrue($calls->isConnection('Illuminate\\Database\\ConnectionResolverInterface'));
        self::assertFalse($calls->isConnection('Illuminate\\Support\\Collection'));
    }

    public function testConnectionCallAliasesTheTableAndKeepsUnknownTablesOpen(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $state = new QueryState(['dialect' => Domain::literal('sqlite')]);
        $aliased = $calls->connectionCall('table', [Domain::literal('users'), Domain::literal('u')], $state, new Environment())?->soleObject();
        self::assertNotNull($aliased);
        self::assertSame('users as u', QueryState::from($aliased)->get('table')->soleLiteral()?->value);
        $sub = $calls->connectionCall('table', [Domain::of(new ObjectTerm(BuilderCalls::QUERY))], $state, new Environment())?->soleObject();
        self::assertNotNull($sub);
        self::assertFalse(QueryState::from($sub)->get('problem')->isExact());
    }

    public function testMutateTreatsIdentityCallsAndEagerLoadsWithoutOpeningTheQuery(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $env = new Environment();
        $query = $calls->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite'), 'model' => Domain::literal('User'), 'softDeletes' => Domain::literal(true)]));
        $identities = array_map(static fn (string $method): array => QueryState::from($calls->mutate($query, $method, [], $env)->soleObject() ?? $query)->fields, ['tobase', 'newquery', 'getquery']);
        self::assertSame([$query->state?->named(), $query->state?->named(), $query->state?->named()], $identities);
        $eager = $calls->mutate($query, 'with', [Domain::literal('posts')], $env)->soleObject();
        self::assertNotNull($eager);
        self::assertTrue(QueryState::from($eager)->get('eager')->soleLiteral()?->value);
        $plain = $calls->mutate($calls->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite')])), 'with', [Domain::literal('posts')], $env)->soleObject();
        self::assertNotNull($plain);
        self::assertFalse(QueryState::from($plain)->get('problem')->isExact());
        $restored = $calls->mutate($calls->mutate($query, 'onlytrashed', [], $env)->soleObject() ?? $query, 'withouttrashed', [], $env)->soleObject();
        self::assertNotNull($restored);
        self::assertNull(QueryState::from($restored)->string('trashed'));
    }

    public function testExecutionRetainsTheWindowOfSingleRowReadsAndTheirReturnTypes(): void
    {
        $calls = new BuilderCalls(new ProgramIndex(), dialects: \SqlCatalog\Facade\Builtins::dialects());
        $env = new Environment();
        $query = $calls->allocate(BuilderCalls::QUERY, new QueryState(['dialect' => Domain::literal('sqlite'), 'key' => Domain::literal('id')]));
        $env->objects()->remember($query);
        self::assertSame('mixed', $calls->execution(Domain::of($query), 'value', [Domain::literal('email')], $env)->type()->display());
        self::assertSame(1, QueryState::from($env->objects()->read(Domain::of($query))->soleObject() ?? $query)->get('limit')->soleLiteral()?->value);
        $calls->execution(Domain::of($query), 'sole', [], $env);
        self::assertSame(2, QueryState::from($env->objects()->read(Domain::of($query))->soleObject() ?? $query)->get('limit')->soleLiteral()->value);
        self::assertSame('stdClass', $calls->execution(Domain::of($query), 'findorfail', [QueryState::list([Domain::literal(1)])], $env)->type()->display());
        self::assertSame('"id" in (?)', QueryState::from($env->objects()->read(Domain::of($query))->soleObject() ?? $query)->items('where')[0]->soleLiteral()?->value);
        self::assertSame('Illuminate\\Pagination\\LengthAwarePaginator', $calls->execution(Domain::of($query), 'paginate', [], $env)->type()->display());
        self::assertArrayNotHasKey('problem', QueryState::from($env->objects()->read(Domain::of($query))->soleObject() ?? $query)->fields);
    }
}
