<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\Configuration;
use Deriver\Query\Budget;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Guards against incorrect dependency binding, correlation, and retention.
 */
#[CoversNothing]
#[Medium]
final class CandidateIntegrityTest extends TestCase
{
    /**
     * Verifies g01 Overwritten Local Definitions Do Not Become Candidates.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG01OverwrittenLocalDefinitionsDoNotBecomeCandidates(): void
    {
        $session = CandidateContractTest::session('function target(){$x=1;$x=2;observe($x);}');
        self::assertSame([2], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies g02 And G03 Property Declarations And Inherited Methods Remain Distinct.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG02AndG03PropertyDeclarationsAndInheritedMethodsRemainDistinct(): void
    {
        $session = CandidateContractTest::session('class ParentRepo {protected string $table="ledger";function archive(){$this->table="archive";}function sql(){return "SELECT ".$this->table;}} class ChildRepo extends ParentRepo {function sql(){return parent::sql();}} class OtherRepo {private $table="wrong";function archive(){$this->table="wrong2";}}');
        self::assertSame(['SELECT ledger', 'SELECT archive'], CandidateContractTest::native($session->derive(new ReturnQuery('ChildRepo::sql')), 'return'));
    }

    /**
     * Verifies g04 Caller Contexts Do Not Mix Identically Named Parameters.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG04CallerContextsDoNotMixIdenticallyNamedParameters(): void
    {
        $session = CandidateContractTest::session('function one($x){return $x+1;}function two($x){return $x+2;}function target(){observe(one(3)+two(5));}');
        self::assertSame([11], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies g05 Preserves Branch Pairs Without Inventing Their Cartesian Product.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG05PreservesBranchPairsWithoutInventingTheirCartesianProduct(): void
    {
        $session = CandidateContractTest::session('function target($flag){if($flag){$table="users";$column="name";}else{$table="orders";$column="total";}observe($table.".".$column);}');
        self::assertSame(['users.name', 'orders.total'], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies g06 Keeps Known Branches And Closes Recursive Dependencies.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG06KeepsKnownBranchesAndClosesRecursiveDependencies(): void
    {
        $session = CandidateContractTest::session('function recursive($flag){if($flag){return "head".recursive($flag);}return "tail";}');
        $result = $session->derive(new ReturnQuery('recursive'));
        self::assertContains('CYCLE', array_column($result->frontiers, 'code'));
        self::assertContains('tail', array_map(static fn ($value) => $value->isConcrete() ? $value->native() : null, CandidateContractTest::values($result, 'return')));
        self::assertNotContains('head', array_map(static fn ($value) => $value->isConcrete() ? $value->native() : null, CandidateContractTest::values($result, 'return')));
    }

    /**
     * Verifies concrete Recursive Inputs Are Simplified To Their Finite Value.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testConcreteRecursiveInputsAreSimplifiedToTheirFiniteValue(): void
    {
        $session = CandidateContractTest::session('function total($n){if($n===0){return 0;}return $n+total($n-1);}function target(){observe(total(4));}');
        self::assertSame([10], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies finite Loop Derives Only The Demanded Recurrence.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFiniteLoopDerivesOnlyTheDemandedRecurrence(): void
    {
        $session = CandidateContractTest::session('function heavy(){throw new RuntimeException;}function target(){$sum=0;for($i=0;$i<4;$i++){$unused=heavy();$sum+=$i;}observe($sum);}');
        $result = CandidateContractTest::argument($session);
        self::assertSame([6], CandidateContractTest::native($result));
        self::assertSame(0, $result->statistics->bodyExpansions);
    }

    /**
     * Verifies g07 Follows A Write Through A Statically Bound Reference Argument.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG07FollowsAWriteThroughAStaticallyBoundReferenceArgument(): void
    {
        $session = CandidateContractTest::session('function change(&$x){$x=7;}function target(){$x=1;$unrelated=9;change($x);observe($x+$unrelated);}');
        self::assertSame([16], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies g08 Uses The Definition At The Read Instead Of Restoring The Original Type.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG08UsesTheDefinitionAtTheReadInsteadOfRestoringTheOriginalType(): void
    {
        $session = CandidateContractTest::session('function target(PDO $pdo){$pdo=12;observe($pdo);}');
        self::assertSame([12], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies g09 Preserves The Children And Source Of An Unsupported Operation.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG09PreservesTheChildrenAndSourceOfAnUnsupportedOperation(): void
    {
        $session = CandidateContractTest::session('function target($x){observe(eval("known".$x));}');
        $value = CandidateContractTest::values(CandidateContractTest::argument($session))[0];
        self::assertSame('operation', $value->kind);
        self::assertSame('candidate.php', $value->attributes['source']);
        self::assertSame('concat', $value->operands[0]->kind);
        self::assertSame('known', $value->operands[0]->operands[0]->literal);
    }

    /**
     * Verifies g10 And G11 Share Dependencies Across Observations With Unresolved Siblings.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG10AndG11ShareDependenciesAcrossObservationsWithUnresolvedSiblings(): void
    {
        $session = CandidateContractTest::session('function helper(){return 3+4;}function target($x){$v=helper();observe($v+$x);observe($v+1);}');
        $sites = $session->callsTo('observe');
        $first = $session->derive(new ValueQuery($sites[0]->argument(0)));
        $second = $session->derive(new ValueQuery($sites[1]->argument(0)));
        self::assertNotEmpty($first->frontiers);
        self::assertSame([8], CandidateContractTest::native($second));
        self::assertGreaterThan(0, $second->statistics->sharedNodeHits);
        self::assertSame(0, $second->statistics->bodyExpansions);
    }

    /**
     * Verifies g12 Deeper Queries Do Not Reuse An Unfinished Shallow Answer.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG12DeeperQueriesDoNotReuseAnUnfinishedShallowAnswer(): void
    {
        $session = CandidateContractTest::session('function target(){$name="users";$table=$name;$sql="SELECT ".$table;observe($sql);}');
        $shallow = CandidateContractTest::argument($session, new Budget(maxDepth: 1));
        $deep = CandidateContractTest::argument($session);
        self::assertContains('DEPTH_LIMIT', array_column($shallow->frontiers, 'code'));
        self::assertSame(['SELECT users'], CandidateContractTest::native($deep));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG12SourceAndModelChangesDoNotReuseAnotherSnapshot(): void
    {
        $analyzer = new \Deriver\Analyzer();
        $results = [];
        $snapshots = [];
        foreach ([1, 2] as $version) {
            $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('answer', (string) $version, 'answer'), new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::literal(\Deriver\Value\Term::constant($version)))]));
            $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('source.php', '<?php function target(){return answer();}')]);
            $session = $analyzer->open($input, new Configuration(models: [$model]));
            $results[] = CandidateContractTest::native($session->derive(new ReturnQuery('target')), 'return');
            $snapshots[] = $session->snapshot()->id;
            $changed = $analyzer->open(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('source.php', '<?php function target(){return ' . $version . ';}')]));
            self::assertSame([$version], CandidateContractTest::native($changed->derive(new ReturnQuery('target')), 'return'));
        }
        self::assertSame([[1], [2]], $results);
        self::assertNotSame($snapshots[0], $snapshots[1]);
    }

    /**
     * Verifies g13 And G14 Retention Is Bounded And Released Graphs Remain Immutable.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testG13AndG14RetentionIsBoundedAndReleasedGraphsRemainImmutable(): void
    {
        $session = CandidateContractTest::session('function target(){$x=7;observe($x);}', new Configuration(candidateCacheEntries: 4, retainedResults: 0));
        $first = CandidateContractTest::argument($session, new Budget(maxDepth: 0));
        $before = $first->toJson();
        for ($depth = 1; $depth <= 40; $depth++) {
            $result = CandidateContractTest::argument($session, new Budget(maxDepth: $depth));
            self::assertLessThanOrEqual(4, $result->statistics->retainedNodes);
        }
        $session->release();
        self::assertSame($before, $first->toJson());
        self::assertSame([7], CandidateContractTest::native(CandidateContractTest::argument($session)));
    }

    /**
     * Verifies an Interrupted Dependency Does Not Destroy The Other Tuple Member.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testAnInterruptedDependencyDoesNotDestroyTheOtherTupleMember(): void
    {
        $session = CandidateContractTest::session('function target(PDO $pdo,$x){$pdo->query("SELECT ".$x);}', new Configuration(resources: new ResourceLimits(seconds: 0.000000001)));
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver, 'sql' => $site->argument(0)]));
        $values = $result->normalOutcomes[0]->values;
        self::assertSame('PDO', $values['receiver']->attributes['type']);
        self::assertSame('SELECT ', $values['sql']->operands[0]->literal);
        self::assertContains('TIME_LIMIT', array_column($result->frontiers, 'code'));
    }
}
