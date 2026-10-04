<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\PlanModel;
use Tests\Fake\SelectiveModel;
use WeakReference;

/**
 * Checks dependency boundaries beyond the minimum observation examples.
 */
#[CoversNothing]
#[Small]
final class CandidateDependenciesTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAliasesAndElementWritesRetainOnlyTheLatestDefinitions(): void
    {
        $session = CandidateContractTest::session('function target(){$x=1;$y=&$x;$y=7;$a=[0];$a[0]=2;observe([$x,$a]);}');
        self::assertSame([[7,[2]]], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownReferenceEffectsKeepThePriorValueAndCallIdentity(): void
    {
        $session = CandidateContractTest::session('function target(){$x=1;$safe=9;missing($x);observe([$x,$safe]);}');
        $result = CandidateContractTest::argument($session);
        $value = CandidateContractTest::values($result)[0];
        self::assertSame('call-write', $value->operands[0]->kind);
        self::assertSame('missing', $value->operands[0]->literal);
        self::assertSame(1, $value->operands[0]->operands[0]->literal);
        self::assertSame(9, $value->operands[1]->literal);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testKnownArrayUnpacksKeepPhpKeyAndOverwriteRules(): void
    {
        $session = CandidateContractTest::session('function target(){$a=[-2=>4,"a"=>5];observe([1,...$a,"a"=>9,...[7]]);}');
        self::assertSame([[0 => 1,1 => 4,'a' => 9,2 => 7]], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDemandModelSelectsWithOnlyItsRequestedInput(): void
    {
        $session = CandidateContractTest::session('function heavy($mode,$unused){return body();}function ignored(){return slow();}function target(){observe(heavy("fast",ignored())+5);}', new Configuration(models: [new SelectiveModel()]));
        $result = CandidateContractTest::argument($session);
        self::assertSame([15], CandidateContractTest::native($result));
        self::assertSame(0, $result->statistics->bodyExpansions);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDemandModelDeclinesExplicitlyBeforeSourceExpansion(): void
    {
        $session = CandidateContractTest::session('function heavy($mode,$unused){return 3;}function target(){observe(heavy("source",unknown()));}', new Configuration(models: [new SelectiveModel()]));
        $result = CandidateContractTest::argument($session);
        self::assertSame([3], CandidateContractTest::native($result));
        self::assertSame(['heavy' => 1], $result->statistics->expandedBodies);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplicitBindingsFlowBackwardsAcrossCallerBoundaries(): void
    {
        $session = CandidateContractTest::session('function child($table){observe("SELECT ".$table);}function entry($name){child($name);}');
        $site = $session->callsTo('observe')[0];
        foreach (['users','orders'] as $table) {
            $scope = QueryScope::fromEntrypoints([new EntryPoint('entry', [Term::constant($table)])]);
            self::assertSame(['SELECT '.$table], CandidateContractTest::native($session->derive(new ValueQuery($site->argument(0), scope: $scope))));
        }
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReassignedReceiversUseTheNewClassAndBoundParameters(): void
    {
        $session = CandidateContractTest::session('class A{function get(){return 1;}}class B{function get(){return 2;}}function target(A $x){$x=new B;observe($x->get());}');
        self::assertSame([2], CandidateContractTest::native(CandidateContractTest::argument($session)));
        $bound = CandidateContractTest::session('class B{public $x=7;function get(){return $this->x;}}function useIt($object){observe($object->get());}function caller(){useIt(new B);}');
        self::assertSame([7], CandidateContractTest::native(CandidateContractTest::argument($bound)));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModelStateFollowsOnlyWritesToTheRequestedReceiverSlot(): void
    {
        $set = new PlanModel(new ModelDescriptor('builder.table', '1', 'Builder::table', new Signature([new Parameter('table')])), new SemanticPlan([Action::write('builder.table', Expression::parameter('table')), Action::returns(Expression::receiver())], writes: ['builder.table']));
        $get = new PlanModel(new ModelDescriptor('builder.sql', '1', 'Builder::sql'), new SemanticPlan([Action::returns(Expression::binary('.', Expression::literal(Term::constant('SELECT ')), Expression::state('builder.table')))], reads: ['builder.table']));
        $session = CandidateContractTest::session('class Builder{}function target(){$a=new Builder;$b=new Builder;$a->table("users");$b->table("orders");observe($a->sql());}', new Configuration(models: [$set,$get], stateSlots: [new StateSlot('builder.table', 'string', Term::constant('default'))]));
        $result = CandidateContractTest::argument($session);
        self::assertSame(['SELECT users'], CandidateContractTest::native($result));
        self::assertSame(0, $result->statistics->bodyExpansions);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEnumerationBoundsDoNotPoisonALaterLargerQuery(): void
    {
        $session = CandidateContractTest::session('function target($a,$b){$x=$a?1:2;$y=$b?10:20;observe($x+$y);}');
        $limited = CandidateContractTest::argument($session, new Budget(partitions: 1));
        self::assertContains('ENUMERATION_LIMIT', array_column($limited->frontiers, 'code'));
        $values = CandidateContractTest::native(CandidateContractTest::argument($session));
        sort($values);
        self::assertSame([11,12,21,22], $values);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReleaseDropsTheSessionOwnershipOfResults(): void
    {
        $session = CandidateContractTest::session('function target(){observe(7);}');
        $result = CandidateContractTest::argument($session);
        $reference = WeakReference::create($result);
        unset($result);
        self::assertNotNull($reference->get());
        $session->release();
        gc_collect_cycles();
        self::assertNull($reference->get());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testConditionalReceiverAssignmentsPreserveBothDeclarations(): void
    {
        $s = CandidateContractTest::session('class A{function get(){return 1;}}class B{function get(){return 2;}}function target($flag){if($flag){$x=new A;}else{$x=new B;}observe($x->get());}');
        self::assertSame([1,2], CandidateContractTest::native(CandidateContractTest::argument($s)));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testGlobalBindingsRemainExplicitDependencies(): void
    {
        $s = CandidateContractTest::session('function target(){global $prefix;observe($prefix."users");}', new Configuration(environment:['global:prefix' => Term::constant('app_')]));
        self::assertSame(['app_users'], CandidateContractTest::native(CandidateContractTest::argument($s)));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testASelectedLocalDoesNotRequireItsEnclosingBranchToExecute(): void
    {
        $s = CandidateContractTest::session('function target(){if(false){$sql="SELECT 1";observe($sql);}}');
        self::assertSame(['SELECT 1'], CandidateContractTest::native(CandidateContractTest::argument($s)));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testPostConditionLoopsKeepEarlierUnknownDependencies(): void
    {
        $s = CandidateContractTest::session('function target($columns){$sql="SELECT ";foreach($columns as $column){$sql.=$column;}do{$sql.=" FROM t";}while(false);observe($sql);}');
        $result = CandidateContractTest::argument($s);
        self::assertNotEmpty($result->normalOutcomes);
        $encoded = serialize($result->candidateGraph);
        self::assertStringContainsString('SELECT ', $encoded);
        self::assertStringContainsString(' FROM t', $encoded);
        self::assertStringContainsString('CYCLE', $encoded);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAReplacementAlsoSuppressesTheSourceReferenceWrite(): void
    {
        $model = new PlanModel(new ModelDescriptor('change', '1', 'change', new Signature([new Parameter('x')])), new SemanticPlan([Action::returns(Expression::literal(Term::constant(10)))]));
        $session = CandidateContractTest::session('function change(&$x){$x=7;}function target(){$x=1;change($x);observe($x);}', new Configuration(models: [$model]));
        $result = CandidateContractTest::argument($session);
        self::assertSame([1], CandidateContractTest::native($result));
        self::assertSame(0, $result->statistics->bodyExpansions);
        self::assertSame(1, $result->statistics->modelApplications);
    }
}
