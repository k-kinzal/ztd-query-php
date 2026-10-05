<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Regression examples use public observations and independently stated PHP outcomes.
 */
#[CoversNothing]
#[Medium]
final class CatalogFeedbackTest extends TestCase
{
    /**
     * @param string $source Captured PHP
     * @param list<int|string> $expected Candidate values
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('observations')]
    public function testRetainsAllObservedCandidates(string $source, array $expected): void
    {
        $session = Analysis::session('<?php function sink($sql){} ' . $source);
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new ValueQuery($call->argument(0)));
        $actual = array_values(array_unique(array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes), SORT_REGULAR));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @return array<string, array{string, list<int|string>}> Independent source fixtures and expected observations
     */
    public static function observations(): array
    {
        return [
            'foreach' => ['function target(){foreach(["a","b","c"] as $t){sink("SELECT $t");}}', ['SELECT a', 'SELECT b', 'SELECT c']],
            'foreach with finally' => ['function target(){foreach(["a","b","c"] as $t){try{sink($t);}finally{$x=1;}}}', ['a','b','c']],
            'for count' => ['function target(){$a=[1,2,3];for($i=0;$i<count($a);$i++){sink("SELECT $i");}}', ['SELECT 0', 'SELECT 1', 'SELECT 2']],
            'repeated pure operand orders' => ['function target(){$a=[1,2,3,4,5,6,7,8,9,10];for($i=0;$i<count($a);$i++)sink($i);}', range(0, 9)],
            'two calls' => ['function pick($x){return $x;} function target(){sink("SELECT ".pick("id")." FROM ".pick("users"));}', ['SELECT id FROM users']],
            'interacting effects' => ['function target(){$n=1;sink($n++ + $n);}', [2,3]],
            'known dynamic name' => ['function target(){$sql="old";$n="sql";$$n="new";sink($sql);}', ['new']],
            'block and echo' => ['function target(){$sql="SELECT 1";{echo "ignored";}sink($sql);}', ['SELECT 1']],
            'unpacked iteration' => ['function target(){$a=["a","b"];foreach([...$a,"c"] as $v)sink($v);}', ['a','b','c']],
            'script reference' => ['$a="old";$b=&$a;$b="new";sink($a);', ['new']],
        ];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('boundaries')]
    public function testUnrelatedTypedArgumentsSurviveUnknownOperandEffects(string $operation): void
    {
        $session = Analysis::session('<?php function sink($value){} function target($x,string $sql){' . $operation . ';sink($sql);}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame('parameter', $outcome->values['value']->kind);
            self::assertSame('string', $outcome->values['value']->attributes['type']);
        }
    }

    /**
     * @return list<array{string}> Independent source fixtures and expected observations
     */
    public static function boundaries(): array
    {
        return [['$s="A".$x'], ['$s=(string)$x'], ['$s=$x["key"]'], ['$s=UNKNOWN_CONSTANT']];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('symbolWrites')]
    public function testSymbolTableWritesCannotLeaveStaleLocals(string $operation): void
    {
        $session = Analysis::session('<?php function sink($value){} function target($n){$sql="SELECT 1";' . $operation . ';sink($sql);}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertNotEmpty($result->frontiers);
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertFalse($outcome->values['value']->isConcrete());
        }
    }

    /**
     * @return list<array{string}> Independent source fixtures and expected observations
     */
    public static function symbolWrites(): array
    {
        return [['$$n="SELECT 2"'], ['include $n'], ['eval($n)'], ['extract($n)']];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testFutureLocalsAfterEvalAreUnknown(): void
    {
        $result = Analysis::returns('<?php function target($code){eval($code);return $injected;}');
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnknownArrayKeysRetainEverySlotAndAbsence(): void
    {
        $result = Analysis::returns('<?php function target(string $key){return ["a"=>"users","b"=>"posts"][$key];}');
        $values = array_unique(array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR);
        self::assertEqualsCanonicalizing(['users','posts',null], $values);
        self::assertSame(['PHP_WARNING'], array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testRepeatedUnknownArrayKeysStayCorrelated(): void
    {
        $result = Analysis::returns('<?php function target(string $key){$a=["a"=>1,"b"=>2];return [$a[$key],$a[$key]];}');
        foreach ($result->normalOutcomes as $outcome) {
            $value = $outcome->values['return']->native();
            self::assertIsArray($value);
            self::assertSame($value[0], $value[1]);
        }
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSuperglobalReadPreservesItsInputDependency(): void
    {
        $result = Analysis::returns('<?php function target(){return $_GET["q"];}');
        self::assertSame('array-read', $result->normalOutcomes[0]->values['return']->kind);
        self::assertSame('superglobal:_GET', $result->normalOutcomes[0]->values['return']->operands[0]->literal);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testExitMakesFollowingObservationUnreachableAcrossCallsAndFinally(): void
    {
        $session = Analysis::session('<?php function sink($x){} function stop(){try{exit;}finally{sink(1);}} function target(){try{stop();}finally{sink(3);}sink(2);}');
        foreach ($session->callsTo('sink') as $call) {
            $result = $session->derive(new ValueQuery($call->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('target')])));
            self::assertSame([], $result->normalOutcomes);
            self::assertSame('unreachable', $result->reachability);
        }
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testExplicitGlobalEnvironmentSuppliesTypedReceivers(): void
    {
        $session = Analysis::session('<?php final class DB {function query(){return "SELECT 1";}} function target(){global $db;return $db->query();}', new Configuration(environment: ['global:db' => Term::parameter('db', 'DB')]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('SELECT 1', $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testTypedMagicAndNullableReceiversKeepKnownImplementations(): void
    {
        $session = Analysis::session('<?php final class DB {function __call($name,$args){return $args[0];}} function target(?DB $db){return $db?->query("SELECT 1");}');
        $result = $session->derive(new ReturnQuery('target'));
        self::assertEqualsCanonicalizing([null,'SELECT 1'], array_unique(array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSourceDeclarationsTakePrecedenceOverStubsInEitherFileOrder(): void
    {
        foreach ([['a.php','z.php'], ['z.php','a.php']] as [$sourcePath,$stubPath]) {
            $session = (new Analyzer())->open(new ProjectInput([
                new SourceFile($stubPath, '<?php class DB {function query(){}} function helper(){}', true),
                new SourceFile($sourcePath, '<?php class DB {function query(){return "source";}} function helper(){return 3;} function target(){return [(new DB)->query(),helper()];}'),
            ]));
            self::assertSame([], $session->snapshot()->diagnostics);
            self::assertSame(['source',3], $session->derive(new ReturnQuery('target'))->candidates[0]->term->native());
        }
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testActualEntrypointsBindClosureCapturesAndObjectInitialization(): void
    {
        $session = Analysis::session('<?php function sink($x){} class Repo {function __construct(public string $table){} function query(){sink($this->table);}} function entry(){$table="users";$run=function()use($table){$r=new Repo($table);$r->query();};$run();}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('entry')])));
        self::assertSame('users', $result->normalOutcomes[0]->values['value']->native());
        self::assertSame([], $result->frontiers);
    }
    /**
     * @throws JsonException If fixture inputs cannot be encoded
     */
    public function testConfiguredSuperglobalIsSharedAcrossFunctionScopes(): void
    {
        $session = Analysis::session('<?php function target(){return $_GET["table"];} ', new Configuration(environment: ['global:_GET' => Term::fromNative(['table' => 'users'])]));
        self::assertSame('users', $session->derive(new ReturnQuery('target'))->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If fixture inputs cannot be encoded
     */
    public function testMixedArrayKeyIncludesTypeErrorAndAllValidSlots(): void
    {
        $result = Analysis::returns('<?php function target($key){return ["a"=>1,"b"=>2][$key];}');
        self::assertEqualsCanonicalizing([1,2,null], array_map(static fn ($outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertContains('TypeError', array_map(static fn ($outcome) => $outcome->exception->literal, $result->exceptionalOutcomes));
    }

    /**
     * @throws JsonException If fixture inputs cannot be encoded
     */
    public function testDynamicConstantDefinitionCannotMakeSubsequentDefinitionCertain(): void
    {
        $result = Analysis::returns('<?php function target(string $name){define($name,"old");define("TABLE","new");return TABLE;}');
        self::assertFalse($result->normalOutcomes[0]->values['return']->isConcrete());
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
    }

}
