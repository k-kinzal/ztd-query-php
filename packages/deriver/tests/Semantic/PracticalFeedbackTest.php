<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\Candidates;

/**
 * Consumer feedback reproduced through ordinary public queries, without framework heuristics.
 */
#[CoversNothing]
#[Medium]
final class PracticalFeedbackTest extends TestCase
{
    /**
     * @param string $call Call syntax
     * @param bool $closed World assumption
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('inheritedCalls')]
    public function testMissingAncestorsRemainOpen(string $call, bool $closed): void
    {
        $session = Analysis::session('<?php final class T extends Vendor\Base { function f(){' . $call . ';sink("SELECT 1");}} function sink($sql){}', new Configuration(closedWorld: $closed));
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertContains('SELECT 1', array_map(static fn ($outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertContains('OPEN_DISPATCH', array_column($result->frontiers, 'code'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function inheritedCalls(): iterable
    {
        foreach (['$this->helper()', 'self::helper()', 'static::helper()', 'parent::helper()'] as $call) {
            foreach ([true, false] as $closed) {
                yield $call . ':' . (int) $closed => [$call, $closed];
            }
        }
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testACompletelyKnownMissingMethodStillThrows(): void
    {
        $result = Candidates::sink('final class T { function f(){self::missing();sink("unreachable");}}');
        self::assertSame([], $result->normalOutcomes);
        self::assertNotEmpty($result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testLargeLiteralArrayIsExactUnderDefaultLimits(): void
    {
        $values = range(0, 8191);
        $result = Analysis::returns('<?php function target(){return [' . implode(',', $values) . '];}');
        self::assertSame([], $result->frontiers);
        self::assertSame($values, $result->normalOutcomes[0]->values['return']->native());
        self::assertNotNull($result->definite());
        self::assertLessThan(100, $result->statistics->transfers);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testLiteralKeyCollisionsAndAppendOrderingRemainPhpExact(): void
    {
        $result = Analysis::returns('<?php function target(){return ["1"=>"first",1=>"second","x"=>false,null=>true,"next",0=>null,"last"];}');
        self::assertSame([1 => 'second','x' => false,'' => true,2 => 'next',0 => null,3 => 'last'], $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testUnknownKeySelectsTheKnownSpreadHeadAndAnUnknownRemainder(): void
    {
        $result = Candidates::sink('function target(array $more, int $k){sink(["users",...$more][$k]);}');
        self::assertTrue(Candidates::contained($result, 'users'));
        self::assertNotEmpty(array_filter(Candidates::values($result), static fn (Term $value): bool => $value->literal === 'users'));
        self::assertNotEmpty(array_filter(Candidates::values($result), static fn (Term $value): bool => !$value->isConcrete()));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testBudgetInterruptionRetainsTheObservedStringShape(): void
    {
        $session = Analysis::session('<?php function sink($sql){} function target($x){unknown();sink("SELECT * FROM " . $x . " WHERE id = 5");}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), budget: new Budget(transfers: 1)));
        self::assertSame('SELECT * FROM ', Candidates::prefix($result->normalOutcomes[0]->values['value']));
        self::assertNull($result->definite());
        self::assertContains('BUDGET_EXCEEDED', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testEntryCapturesBindUncalledClosures(): void
    {
        $session = Analysis::session('<?php $f=function(int $id) use($table){return "$table:$id";};');
        $symbols = array_values(array_filter($session->declarations()->symbols(), static fn (string $name): bool => str_starts_with($name, 'closure:')));
        $scope = QueryScope::fromEntrypoints([new EntryPoint($symbols[0], [Term::constant(5)], captures: ['table' => Term::constant('users')])]);
        $result = $session->derive(new ReturnQuery($symbols[0], $scope));
        self::assertSame('users:5', $result->normalOutcomes[0]->values['return']->native());
        $other = QueryScope::fromEntrypoints([new EntryPoint($symbols[0], [Term::constant(5)], captures: ['table' => Term::constant('posts')])]);
        self::assertSame('posts:5', $session->derive(new ReturnQuery($symbols[0], $other))->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     * @throws RuntimeException If the published result schema cannot be read
     */
    public function testEntryCaptureSerializationPreservesRedactionAndSchema(): void
    {
        $session = Analysis::session('<?php $f=fn()=>$input;');
        $symbol = array_values(array_filter($session->declarations()->symbols(), static fn (string $name): bool => str_starts_with($name, 'closure:')))[0];
        $query = new ReturnQuery($symbol, QueryScope::fromEntrypoints([new EntryPoint($symbol, captures: ['input' => Term::constant('hidden-capture', true)], symbolicArguments: true)]));
        $json = $session->derive($query)->toJson();
        self::assertStringNotContainsString('hidden-capture', $json);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($json));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testOmittedClosureCapturesRemainSymbolic(): void
    {
        $session = Analysis::session('<?php $f=function()use($table,$suffix){return $table.$suffix;};');
        $symbol = array_values(array_filter($session->declarations()->symbols(), static fn (string $name): bool => str_starts_with($name, 'closure:')))[0];
        $result = $session->derive(new ReturnQuery($symbol, QueryScope::fromEntrypoints([new EntryPoint($symbol, captures: ['table' => Term::constant('users')])])));
        self::assertNull($result->definite());
        self::assertSame('users', Candidates::prefix($result->normalOutcomes[0]->values['return']));
        self::assertNotContains('PHP_WARNING', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testCapturesCannotSilentlyCreateUncapturedLocalVariables(): void
    {
        $session = Analysis::session('<?php function target(){return $typo;}');
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', captures: ['typo' => Term::constant(1)])])));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testSymbolicEntryArgumentsEnumerateEnumsAndHonorExplicitArguments(): void
    {
        $session = Analysis::session('<?php enum E:string{case A="a";case B="b";} function target(E $e,int $id){return $e->value.$id;}');
        $scope = QueryScope::fromEntrypoints([new EntryPoint('target', ['id' => Term::constant(5)], symbolicArguments: true)]);
        $result = $session->derive(new ReturnQuery('target', $scope));
        self::assertSame(['a5','b5'], array_map(static fn ($outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testDynamicCallsAreDiscoverableAndQueryable(): void
    {
        $session = Analysis::session('<?php $f=fn($x)=>$x; $f("users");');
        $calls = $session->callsTo('*');
        self::assertCount(1, $calls);
        self::assertSame('', $calls[0]->target);
        self::assertSame('users', $session->derive(new ValueQuery($calls[0]->argument(0)))->normalOutcomes[0]->values['value']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testRequestInputConversionKeepsScriptGlobals(): void
    {
        $session = Analysis::session('<?php class PDO {function query($sql){}} $pdo=new PDO; $pdo->query("SELECT ". $_GET["id"]); $pdo->query("SELECT 2");');
        $site = $session->callsTo('query')[1];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['sql' => $site->argument(0), 'receiver' => $site->receiver]));
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame('PDO', $outcome->values['receiver']->attributes['class']);
            self::assertSame('SELECT 2', $outcome->values['sql']->native());
        }
        self::assertNotContains('dynamic-string-conversion', array_column($result->frontiers, 'operation'));
        self::assertNotContains('offset-protocol', array_column($result->frontiers, 'operation'));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testStatementCommentsAreAvailableWithoutInterpretingPhpdoc(): void
    {
        $session = Analysis::session('<?php function legacy(){/** @var PDO $db */ global $db; return $db;}');
        $comments = $session->comments('legacy');
        self::assertCount(1, $comments);
        self::assertSame('/** @var PDO $db */', $comments[0]->text);
        self::assertSame('fixture.php', $comments[0]->source->path);
        self::assertGreaterThan(0, $comments[0]->source->start);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testUnknownMiddleFormatKeepsTheSafePrefix(): void
    {
        $result = Candidates::sink('function target(string $table){sink(sprintf("SELECT * FROM $table WHERE id = %d",5));}');
        foreach (Candidates::values($result) as $value) {
            self::assertSame('SELECT * FROM ', Candidates::prefix($value));
        }
        self::assertNotEmpty($result->frontiers);
        self::assertNull($result->definite());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testClosedFunctionSummariesAreSharedAcrossDistinctObservations(): void
    {
        $session = Analysis::session('<?php function helper($x){return $x."users";} function sink($sql){} function target(){$sql=helper("SELECT ");sink($sql);sink($sql." WHERE id=1");}');
        $sites = $session->callsTo('sink');
        $first = $session->derive(new ValueQuery($sites[0]->argument(0)));
        $second = $session->derive(new ValueQuery($sites[1]->argument(0)));
        self::assertSame('SELECT users', $first->normalOutcomes[0]->values['value']->native());
        self::assertSame('SELECT users WHERE id=1', $second->normalOutcomes[0]->values['value']->native());
        self::assertGreaterThan(0, $second->statistics->cacheHits);
        self::assertSame([], $second->frontiers);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testWarmSummariesCannotSkipAnObservationInsideACallee(): void
    {
        $source = '<?php function sink($v){} function helper($x){sink($x);return $x;} function target(){return helper("observed");}';
        $session = Analysis::session($source);
        $session->derive(new ReturnQuery('target'));
        $site = $session->callsTo('sink')[0];
        $query = new ValueQuery($site->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('target')]));
        $warm = $session->derive($query);
        self::assertSame('observed', $warm->normalOutcomes[0]->values['value']->native());
        self::assertSame([], $warm->frontiers);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testInterruptedCalleePreservesAnInaccessibleCallerReceiver(): void
    {
        $session = Analysis::session('<?php function slow($x){return $x?slow($x):1;} function target(PDO $pdo,$x){slow($x);$pdo->query("SELECT 1");}');
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['sql' => $site->argument(0), 'receiver' => $site->receiver], budget: new Budget(recursion: 1, symbolicRecursion: 1)));
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame('SELECT 1', $outcome->values['sql']->native());
            self::assertSame('PDO', $outcome->values['receiver']->attributes['type']);
        }
        self::assertContains('BUDGET_EXCEEDED', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testLoopInterruptionCannotRebindAnObjectPassedByValue(): void
    {
        $session = Analysis::session('<?php function collect(PDO $pdo,array $input){$ids=[];foreach($input as $id){if($id>0){$ids[]=$id;}}} function target(PDO $pdo,array $input){collect($pdo,$input);$pdo->query("SELECT 1");}');
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver], budget: new Budget(partitions: 2, iterations: 2)));
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame('PDO', $outcome->values['receiver']->attributes['type']);
        }
        self::assertNotEmpty($result->frontiers);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testInjectedStaticStateIsAnExplicitEntryAssumption(): void
    {
        $session = Analysis::session('<?php function target(){static $n=0;return ++$n;}', new Configuration(environment: ['static:target:n' => Term::constant(4)]));
        self::assertSame(5, $session->derive(new ReturnQuery('target'))->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testUnpackedArraySuffixSurvivesJoiningAndIteration(): void
    {
        $joined = Candidates::sink('function target(array $x){sink(implode(",",[...$x,"z"]));}');
        self::assertTrue(Candidates::contained($joined, 'z'));
        self::assertNotEmpty(array_filter(Candidates::values($joined), static fn (Term $value): bool => $value->literal === 'z'));
        self::assertNotEmpty($joined->frontiers);
        $iterated = Candidates::sink('function target(array $x){foreach([...$x,"z"] as $v){sink($v);break;}}');
        self::assertNotEmpty(array_filter(Candidates::values($iterated), static fn (Term $value): bool => $value->literal === 'z'));
        self::assertNotEmpty(array_filter(Candidates::values($iterated), static fn (Term $value): bool => !$value->isConcrete()));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testSpreadSuffixCannotPrecedeTheKnownHead(): void
    {
        $result = Candidates::sink('function target(array $x){foreach(["a",...$x,"z"] as $v){sink($v);break;}}');
        self::assertSame(['a'], array_map(static fn (Term $value) => $value->native(), Candidates::values($result)));
    }

}
