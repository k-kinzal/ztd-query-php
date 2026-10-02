<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\PlanModel;

/**
 * Verifies catalog integration through the public analysis and model contracts.
 */
#[CoversNothing]
#[Small]
final class CatalogMetadataTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testObservationExposesEvaluatedReceiverWithoutReevaluation(): void
    {
        $session = Analysis::session('<?php final class DB {function query($sql){}} function target(){$db=new DB;$db->query("SELECT 1");}');
        $call = $session->callsTo('query')[0];
        self::assertNotNull($call->receiver);
        self::assertSame('invoke-method', $call->operation);
        $result = $session->derive(new TupleQuery($call->beforeInvocation(), ['receiver' => $call->receiver,'sql' => $call->argument(0)]));
        self::assertSame('DB', $result->normalOutcomes[0]->values['receiver']->attributes['class']);
        self::assertSame('SELECT 1', $result->normalOutcomes[0]->values['sql']->native());
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testFunctionObservationsRespectNamespaceFallbackAndShadowing(): void
    {
        $session = Analysis::session('<?php namespace {function sink($x){}} namespace App {function target(){sink("global");}} namespace Local {function sink($x){} function target(){sink("local");}}');
        $global = $session->callsTo('sink');
        self::assertCount(1, $global);
        self::assertSame('App\\target', $global[0]->callable);
        self::assertSame('sink', $global[0]->target);
        self::assertNull($global[0]->receiver);
        self::assertCount(1, $session->callsTo('Local\\sink'));
        self::assertCount(2, $session->callsTo('*'));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testDeclarationsAllowCallerInventoryAndExplicitEntrySelection(): void
    {
        $session = Analysis::session('<?php function sink($x){} function query($table){sink("SELECT * FROM ".$table);} function users(){query("users");} function posts(){query("posts");}');
        $symbols = $session->declarations()->symbols();
        self::assertContains('users', $symbols);
        self::assertContains('posts', $symbols);
        $entries = array_map(static fn (\Deriver\Reference\Observation $call): EntryPoint => new EntryPoint($call->callable), $session->callsTo('query'));
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), scope: QueryScope::fromEntrypoints($entries)));
        self::assertEqualsCanonicalizing(['SELECT * FROM users','SELECT * FROM posts'], array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testModelCanReuseSourceNamesReferencesAndComputedDefaults(): void
    {
        $model = new PlanModel(new ModelDescriptor('test.signature', '1', 'query', replaceSource: true, useSourceSignature: true), new SemanticPlan([Action::returns(Expression::parameter('sql'))]));
        $session = Analysis::session('<?php function query(string $sql="SELECT "."1"){return "old";} function target(){return [query(sql:"SELECT 2"),query()];}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(['SELECT 2','SELECT 1'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        $signature = $session->declarations()->signature('query');
        self::assertNotNull($signature);
        self::assertSame('sql', $signature->parameters[0]->name);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testModelReceivesCalledClassSourceAndClassMetadata(): void
    {
        $model = new class () implements CallModel {
            /**
             * @var list<CallDescription> Captured preparation and invocation metadata
             */
            public array $seen = [];
            #[Override]
            public function descriptor(): ModelDescriptor
            {
                return new ModelDescriptor('test.metadata', '1', 'Model::where', replaceSource: true, useSourceSignature: true);
            }
            #[Override]
            public function describe(CallDescription $call): ModelDecision
            {
                $this->seen[] = $call;
                $class = $call->declarations?->class($call->receiverType);
                $table = $class?->properties['table']->default;
                return ModelDecision::handled(new SemanticPlan([Action::returns(Expression::literal($table ?? Term::constant('missing')))]));
            }
        };
        $session = Analysis::session('<?php trait Named {function label(){}} class Model {static function where($id){}} class User extends Model {use Named;protected $table="users";const KIND="user";} function target(){return User::where(id:7);}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('users', $result->normalOutcomes[0]->values['return']->native());
        foreach ($model->seen as $call) {
            self::assertSame('User', $call->receiverType);
            self::assertSame('fixture.php', $call->source?->path);
        }
        $class = $session->declarations()->class('USER');
        self::assertNotNull($class);
        self::assertSame(['Named'], $class->traits);
        self::assertArrayHasKey('label', $class->methods);
        self::assertSame('user', $class->constants['KIND']->native());
        self::assertSame('Model', $class->parent);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testObjectCreationIsSelectedByItsConstructor(): void
    {
        $session = Analysis::session('<?php namespace App; class Repo {function __construct($table){} static function make(){return new self("self");}} class Sub extends Repo {function copy(){return new static("static");} function base(){return new parent("parent");}} function target($class){$a=new Repo("users");$b=new \\Other\\Thing(1);$c=new $class(2);$d=new class {};record("call");} function record($x){}');
        $all = $session->callsTo('*');
        self::assertSame([['new', 'App\\Repo'], ['new', 'static'], ['new', 'App\\Repo'], ['new', 'App\\Repo'], ['new', 'Other\\Thing'], ['invoke', 'App\\record']], array_map(static fn (\Deriver\Reference\Observation $call): array => [$call->operation, $call->target], $all));
        $constructions = $session->callsTo('app\\REPO::__construct');
        self::assertSame(['App\\Repo::make', 'App\\Sub::base', 'App\\target'], array_map(static fn (\Deriver\Reference\Observation $call): string => $call->callable, $constructions));
        self::assertSame([], $session->callsTo('App\\Repo'));
        $users = $constructions[2];
        self::assertNull($users->receiver);
        $result = $session->derive(new TupleQuery($users->beforeInvocation(), ['table' => $users->argument(0)]));
        self::assertSame('users', $result->normalOutcomes[0]->values['table']->native());
        self::assertNotNull($users->returned);
        $created = $session->derive(new ValueQuery($users->returned));
        self::assertSame('App\\Repo', $created->normalOutcomes[0]->values['value']->attributes['class']);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testFrontiersNameGlobalsThatTheEnvironmentSupplies(): void
    {
        $source = '<?php function sink($sql){} function posts(){global $prefix;sink("SELECT * FROM ".$prefix."posts");} sink("SELECT * FROM ".$table);';
        $session = Analysis::session($source);
        $names = [];
        foreach ($session->callsTo('sink') as $call) {
            foreach ($session->derive(new ValueQuery($call->argument(0)))->frontiers as $frontier) {
                array_push($names, ...$frontier->knownDependencies);
            }
        }
        sort($names);
        self::assertSame(['global:prefix', 'global:table'], $names);
        $configured = Analysis::session($source, new Configuration(environment: ['global:prefix' => Term::constant('wp_'), 'global:table' => Term::constant('users')]));
        $values = [];
        foreach ($configured->callsTo('sink') as $call) {
            $result = $configured->derive(new ValueQuery($call->argument(0)));
            self::assertSame([], $result->frontiers);
            $values[] = $result->definite()?->values['value']->native();
        }
        self::assertSame(['SELECT * FROM wp_posts', 'SELECT * FROM users'], $values);
    }
}
