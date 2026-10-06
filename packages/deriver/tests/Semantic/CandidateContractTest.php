<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\AnalysisSession;
use Deriver\Analyzer;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**

 * The observation-rooted contract is tested through the default public API.

 */
#[CoversNothing]
#[Large]
final class CandidateContractTest extends TestCase
{
    /**
     * Opens a captured source fixture using the candidate contract.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public static function session(string $source, Configuration $configuration = new Configuration()): AnalysisSession
    {
        return (new Analyzer())->open(new ProjectInput([new SourceFile('candidate.php', '<?php ' . $source)]), $configuration);
    }

    /**

     * @return list<Term>

     */
    public static function values(CandidateCollection $result, string $slot = 'value'): array
    {
        return array_map(static fn ($candidate): Term => $candidate->term, $result->candidates);
    }

    /**

     * @return list<mixed>

     */
    public static function native(CandidateCollection $result, string $slot = 'value'): array
    {
        return array_map(static fn (Term $value) => $value->native(), self::values($result, $slot));
    }

    /**
     * Selects a fixture observation through the public API.
     */
    public static function argument(AnalysisSession $session, ?Budget $budget = null): CandidateCollection
    {
        return $session->derive(new ValueQuery($session->callsTo('observe')[0]->argument(0), budget: $budget ?? new Budget()));
    }

    /**
     * @return list<\Deriver\Result\Frontier> Test-only traversal of candidate residuals.
     */
    public static function frontiers(CandidateCollection $result): array
    {
        $frontiers = [];
        foreach ($result as $candidate) {
            array_push($frontiers, ...(new \Tests\Fake\CandidateFrontiers())->frontiers($candidate->term, $candidate->evidence[0]->snapshot));
        }
        return $frontiers;
    }

    /**
     * Verifies c01 And C16 Ignore Unrelated Calls And Their Exceptions.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC01AndC16IgnoreUnrelatedCallsAndTheirExceptions(): void
    {
        foreach (['return 42;', 'throw new RuntimeException;', 'return parent::missing();'] as $body) {
            $session = self::session('class Child extends Absent {function heavy($x){' . $body . '} function inspect($input){$unused=$this->heavy($input);observe("SELECT * FROM users");}}');
            $result = self::argument($session);
            self::assertSame(['SELECT * FROM users'], self::native($result));
            self::assertSame(0, $result->statistics->bodyExpansions);
            self::assertSame([], $result->statistics->expandedReferences);
        }
    }

    /**
     * Verifies c02 Keeps An Identifiable Parameter And Its Expression.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC02KeepsAnIdentifiableParameterAndItsExpression(): void
    {
        $result = self::argument(self::session('function sql($table){$sql="SELECT * FROM ".$table;observe($sql);}'));
        self::assertCount(1, $result->candidates);
        $value = self::values($result)[0];
        self::assertSame('concat', $value->kind);
        self::assertSame('SELECT * FROM ', $value->operands[0]->literal);
        $reference = CandidateContractTest::frontiers($result)[0]->residual;
        self::assertNotNull($reference);
        self::assertSame('$table', $reference->literal);
        self::assertSame('sql', $reference->attributes['scope']);
        self::assertSame('candidate.php', $reference->attributes['source']);
        self::assertNotEmpty($reference->attributes['identity']);
        self::assertSame('EXTERNAL_INPUT', $reference->attributes['reason']);
    }

    /**
     * Verifies c03 Finds All Matching Caller Arguments.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC03FindsAllMatchingCallerArguments(): void
    {
        $session = self::session('class Repo {function sql($table){$sql="SELECT * FROM $table";observe($sql);}} function callers(Repo $repo){$repo->sql("users");$repo->sql(table:"orders");}');
        self::assertSame(['SELECT * FROM users', 'SELECT * FROM orders'], self::native(self::argument($session)));
    }

    /**
     * Verifies c04 And C05 Keep Numeric Alternatives And Partial Bindings.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC04AndC05KeepNumericAlternativesAndPartialBindings(): void
    {
        $session = self::session('function numbers(bool $flag,int $bar,int $fuz){if($flag){$foo=5+$bar;}else{$foo=3+$fuz;}observe($foo);}');
        $values = self::values(self::argument($session));
        self::assertCount(2, $values);
        self::assertSame([5, 3], array_map(static fn (Term $value) => $value->operands[0]->literal, $values));
        self::assertSame(['$bar', '$fuz'], array_map(static fn (Term $value) => $value->operands[1]->literal, $values));
        $scope = QueryScope::fromEntrypoints([new EntryPoint('numbers', ['bar' => Term::constant(7)], symbolicArguments: true)]);
        $result = $session->derive(new ValueQuery($session->callsTo('observe')[0]->argument(0), scope: $scope));
        $values = self::values($result);
        self::assertSame(12, $values[0]->native());
        self::assertSame('+', $values[1]->literal);
        self::assertSame('$fuz', $values[1]->operands[1]->literal);
    }

    /**
     * Verifies c06 Collects Property Initializer And Ordinary Method Writes.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC06CollectsPropertyInitializerAndOrdinaryMethodWrites(): void
    {
        $session = self::session('class Repository {private string $table="ledger";function archive(){$this->table="ledger_archive";}function sql(){return "SELECT * FROM ".$this->table;}}');
        self::assertSame(['SELECT * FROM ledger', 'SELECT * FROM ledger_archive'], self::native($session->derive(new ReturnQuery('Repository::sql')), 'return'));
    }

    /**
     * Supplies a declarative replacement for the fixture call.
     */
    public static function model(Expression $value): CallModel
    {
        return new class ($value) implements CallModel {
            /**
             * Captures the dependencies used by this component.
             */
            public function __construct(private readonly Expression $value)
            {
            }
            /**
             * Verifies descriptor.
             */
            public function descriptor(): ModelDescriptor
            {
                return new ModelDescriptor('heavy.override', '1', 'heavy', new Signature([new Parameter('input'), new Parameter('unused')]));
            }
            /**
             * Verifies describe.
             */
            public function describe(CallDescription $call): ModelDecision
            {
                return ModelDecision::handled(new SemanticPlan([Action::returns($this->value)]));
            }
        };
    }

    /**
     * Verifies c07 And C08 Replace The Body And Do Not Demand Unused Inputs.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC07AndC08ReplaceTheBodyAndDoNotDemandUnusedInputs(): void
    {
        foreach (['', 'function heavy($input,$unused){return expensive($input);}'] as $body) {
            $session = self::session($body . 'function target($input){$value=heavy($input,other())+5;observe($value);}', new Configuration(models: [self::model(Expression::literal(Term::constant(10)))]));
            $result = self::argument($session);
            self::assertSame([15], self::native($result));
            self::assertSame(0, $result->statistics->bodyExpansions);
            self::assertSame(1, $result->statistics->modelApplications);
            self::assertArrayNotHasKey('target:$input', $result->statistics->expandedReferences);
        }
    }

    /**
     * Verifies c09 Demands Only The Input Used By The Model.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC09DemandsOnlyTheInputUsedByTheModel(): void
    {
        $session = self::session('function heavy($input,$unused){return expensive();} function unused(){return expensive();} function target(){observe(heavy(7,unused())+5);}', new Configuration(models: [self::model(Expression::parameter('input'))]));
        $result = self::argument($session);
        self::assertSame([12], self::native($result));
        self::assertSame(0, $result->statistics->bodyExpansions);
    }

    /**
     * Verifies c10 Depth Counts Origin Steps Independently Of Syntax.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC10DepthCountsOriginStepsIndependentlyOfSyntax(): void
    {
        $session = self::session('function target($name){$table=$name;$sql="SELECT * FROM ".$table;observe($sql);}');
        foreach ([0 => '$sql', 1 => '$table', 2 => '$name'] as $depth => $name) {
            $result = self::argument($session, new Budget(maxDepth: $depth));
            $residual = CandidateContractTest::frontiers($result)[0]->residual;
            self::assertNotNull($residual);
            self::assertSame($name, $residual->literal);
            self::assertSame('DEPTH_LIMIT', CandidateContractTest::frontiers($result)[0]->code);
            self::assertSame($depth === 0 ? 'deferred' : 'concat', self::values($result)[0]->kind);
        }
        $constant = self::argument(self::session('function target(){observe(12);}'), new Budget(maxDepth: 0));
        self::assertSame([12], self::native($constant));
    }

    /**
     * Verifies c11 And C12 Keep Known Siblings And Receiver Type At A Boundary.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC11AndC12KeepKnownSiblingsAndReceiverTypeAtABoundary(): void
    {
        $session = self::session('function target(PDO $pdo,$bar){$sql="SELECT n = ".(5+$bar);$pdo->query($sql);}');
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver, 'sql' => $site->argument(0)], budget: new Budget(maxDepth: 1)));
        $values = $result->candidates[0]->term->operands;
        self::assertSame('PDO', $values['receiver']->attributes['type']);
        self::assertSame('concat', $values['sql']->kind);
        self::assertSame('SELECT n = ', $values['sql']->operands[0]->literal);
        self::assertSame(5, $values['sql']->operands[1]->operands[0]->operands[0]->literal);
    }

    /**
     * Verifies c13 Evaluates Closed Unary And Binary Expressions.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC13EvaluatesClosedUnaryAndBinaryExpressions(): void
    {
        self::assertSame([-20], self::native(self::argument(self::session('function target(){observe(-(2+3)*4);}'))));
    }

    /**
     * Verifies c14 All Large Array Spellings Use The Same General Semantics.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testC14AllLargeArraySpellingsUseTheSameGeneralSemantics(): void
    {
        foreach (['0', '-1', '"-1"', '-(1+0)'] as $key) {
            $source = 'function target(){return [' . $key . '=>0,' . implode(',', range(1, 8191)) . '];}';
            $result = self::session($source)->derive(new ReturnQuery('target'));
            self::assertSame([], CandidateContractTest::frontiers($result), $key);
            self::assertSame([$key === '0' ? range(0, 8191) : array_combine(range(-1, 8190), range(0, 8191))], self::native($result, 'return'), $key);
            self::assertLessThan(20000, $result->statistics->constructedNodes);
        }
    }

    /**
     * Verifies c15 Retains A Whole Array With An Unknown Middle Element.
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testC15RetainsAWholeArrayWithAnUnknownMiddleElement(): void
    {
        $result = self::argument(self::session('function target($x){observe(["head",$x,"tail"]);}'));
        $value = self::values($result)[0];
        self::assertSame('array', $value->kind);
        self::assertSame('head', $value->operands[0]->literal);
        self::assertSame('$x', $value->operands[1]->literal);
        self::assertSame('tail', $value->operands[2]->literal);
    }
}
