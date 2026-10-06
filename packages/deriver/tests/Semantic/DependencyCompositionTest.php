<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Project\Configuration;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\PlanModel;

/**
 * Exercises dependency contracts where PHP features cross resolution boundaries.
 */
#[CoversNothing]
#[Medium]
final class DependencyCompositionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeclaredParentDoesNotFixTheReceiverImplementation(): void
    {
        $session = CandidateContractTest::session('class BaseRepo{function table(){return "base";}}class UserRepo extends BaseRepo{function table(){return "users";}}function sql(BaseRepo $repo){return "SELECT * FROM ".$repo->table();}function target(){observe(sql(new UserRepo));}');
        $result = CandidateContractTest::argument($session);
        self::assertSame(['SELECT * FROM users'], CandidateContractTest::native($result));
        self::assertArrayNotHasKey('BaseRepo::table', $result->statistics->expandedBodies);
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('mutations')]
    public function testPropertyOriginsEvaluateTheStoredValue(string $initial, string $mutation, mixed $expected): void
    {
        $session = CandidateContractTest::session('class Repository{public $value='.$initial.';function change(){$this->value='.$initial.';'.$mutation.'}function get(){return $this->value;}}');
        $result = $session->derive(new ReturnQuery('Repository::get'));
        self::assertContains($expected, CandidateContractTest::native($result, 'return'));
        self::assertSame([], CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function mutations(): iterable
    {
        yield 'concatenate' => ['"ledger"', '$this->value.="_archive";', 'ledger_archive'];
        yield 'add' => ['4', '$this->value+=3;', 7];
        yield 'post-increment' => ['4', '$this->value++;', 5];
        yield 'pre-decrement' => ['4', '--$this->value;', 3];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReassignedRecursiveArgumentIsNotAnUnchangedDependency(): void
    {
        $session = CandidateContractTest::session('function countDown($n){if($n===0){return 0;}$n=$n-1;return 1+countDown($n);}function target(){observe(countDown(4));}');
        $result = CandidateContractTest::argument($session, new Budget(maxDepth: 256));
        self::assertSame([4], CandidateContractTest::native($result));
        self::assertSame([], CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('propertyEntries')]
    public function testReplacementCannotBeBypassedThroughPropertyOrigins(string $symbol, string $entry): void
    {
        $model = new PlanModel(new ModelDescriptor('replacement', '1', $symbol), new SemanticPlan([]));
        $session = CandidateContractTest::session('class Box{public $table="ledger";function __construct(){$this->table="source_only";}function change(){$this->table="method_only";}function tableName(){return $this->table;}}function target(){return (new Box)->tableName();}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery($entry));
        self::assertArrayNotHasKey($symbol, $result->statistics->expandedBodies);
        $values = CandidateContractTest::native($result, 'return');
        self::assertNotContains($symbol === 'Box::__construct' ? 'source_only' : 'method_only', $values);
        self::assertContains('ledger', $values);
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function propertyEntries(): iterable
    {
        yield 'allocation' => ['Box::__construct', 'target'];
        yield 'constructor-origin' => ['Box::__construct', 'Box::tableName'];
        yield 'method-origin' => ['Box::change', 'Box::tableName'];
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('decisions')]
    public function testPropertyOriginsRespectContextualModelDecisions(string $method, string $mode): void
    {
        $symbol = 'Box::'.$method;
        $call = $method === '__construct' ? 'new Box("'.$mode.'")' : '(new Box)->change("'.$mode.'")';
        $source = 'class Box{public $value="initial";function '.$method.'($mode){$this->value="source_only";}function get(){return $this->value;}}function target(){'.$call.';}';
        $session = CandidateContractTest::session($source, new Configuration(models: [new \Tests\Fake\PropertyDecisionModel($symbol)]));
        $result = $session->derive(new ReturnQuery('Box::get'));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
        if ($mode === 'source') {
            self::assertContains('source_only', CandidateContractTest::native($result, 'return'));
            self::assertArrayHasKey($symbol, $result->statistics->expandedBodies);
        } else {
            self::assertArrayNotHasKey($symbol, $result->statistics->expandedBodies);
            if ($mode === 'replace') {
                self::assertSame(['initial'], CandidateContractTest::native($result, 'return'));
                self::assertSame([], CandidateContractTest::frontiers($result));
            } else {
                self::assertContains('UNSUPPORTED_MODEL_CASE', array_column(CandidateContractTest::frontiers($result), 'code'));
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function decisions(): iterable
    {
        foreach (['__construct', 'change'] as $method) {
            foreach (['replace', 'source', 'unsupported'] as $mode) {
                yield $method.'-'.$mode => [$method, $mode];
            }
        }
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testFiniteRecursionWithInsufficientBudgetReportsALimit(): void
    {
        $session = CandidateContractTest::session('function down($n){if($n===0){return 0;}$n-=1;return 1+down($n);}function target(){observe(down(4));}');
        $result = CandidateContractTest::argument($session, new Budget(recursion: 1, maxDepth: 256));
        self::assertContains('RECURSION_LIMIT', array_column(CandidateContractTest::frontiers($result), 'code'));
        self::assertNotContains('CYCLE', array_column(CandidateContractTest::frontiers($result), 'code'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testPropertyOriginDispatchHonorsTheChildReplacement(): void
    {
        $model = new PlanModel(new ModelDescriptor('child-change', '1', 'ChildBox::change'), new SemanticPlan([]));
        $session = CandidateContractTest::session('class Box{public $value="initial";function change(){$this->value="parent_source";}function get(){return $this->value;}}class ChildBox extends Box{function change(){$this->value="child_source";}}function apply(Box $box){$box->change();}function target(){apply(new ChildBox);}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('Box::get'));
        self::assertSame(['initial'], CandidateContractTest::native($result, 'return'));
        self::assertSame([], $result->statistics->expandedBodies);
        self::assertSame([], CandidateContractTest::frontiers($result));
    }

}
