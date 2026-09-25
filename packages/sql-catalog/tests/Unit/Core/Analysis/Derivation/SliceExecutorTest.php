<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\BuiltinCallModel;
use SqlCatalog\Core\Analysis\CallEvaluator;
use SqlCatalog\Core\Analysis\ConstantReader;
use SqlCatalog\Core\Analysis\Derivation\CalleeReturns;
use SqlCatalog\Core\Analysis\Derivation\CallerIndex;
use SqlCatalog\Core\Analysis\Derivation\Callers;
use SqlCatalog\Core\Analysis\Derivation\Deriver;
use SqlCatalog\Core\Analysis\Derivation\EntryBinder;
use SqlCatalog\Core\Analysis\Derivation\FreeNames;
use SqlCatalog\Core\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Core\Analysis\Derivation\PropertyWrites;
use SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps;
use SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses;
use SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\ExternalInput;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Analysis\ReferenceEvaluator;
use SqlCatalog\Core\Analysis\SinkFinder;
use SqlCatalog\Core\Analysis\SinkMatcher;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Php\DeclaredGlobals;
use SqlCatalog\Core\Php\NodeText;
use SqlCatalog\Core\Php\ParsedFile;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Php\TypeReader;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use WeakMap;

#[CoversClass(SliceExecutor::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(CallEvaluator::class)]
#[UsesClass(ConstantReader::class)]
#[UsesClass(CalleeReturns::class)]
#[UsesClass(CallerIndex::class)]
#[UsesClass(Callers::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(EntryBinder::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(PropertyWrites::class)]
#[UsesClass(AssignmentSteps::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(LoopPasses::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(ExpressionEvaluator::class)]
#[UsesClass(ExternalInput::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(ReferenceEvaluator::class)]
#[UsesClass(SinkFinder::class)]
#[UsesClass(SinkMatcher::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(DeclaredGlobals::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
final class SliceExecutorTest extends TestCase
{
    public function testRunSplitsOnATernarySoLaterReadsAgree(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $o = $a ? "ASC" : "DESC"; $sql = "ORDER BY x $o, id $o";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $runs = (new SliceExecutor())->run($steps, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame([
            'o=literal:string:ASC;sql=literal:string:ORDER BY x ASC, id ASC',
            'o=literal:string:DESC;sql=literal:string:ORDER BY x DESC, id DESC',
        ], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
    }

    public function testRunWithALimitOfOneKeepsTheValuesTogether(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $o = $a ? "ASC" : "DESC"; $sql = "BY $o, $o";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $runs = (new SliceExecutor())->run($steps, new Environment(), new FunctionScope('t.php'), $expressions, false, 1);

        self::assertSame([
            'o=literal:string:ASC|literal:string:DESC;sql=literal:string:BY ASC, ASC|literal:string:BY ASC, DESC|literal:string:BY DESC, ASC|literal:string:BY DESC, DESC',
        ], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function providerRunSizesTheLimitFromTheBudget(): array
    {
        return [
            'a budget paying for one run keeps the values together' => [16, 1],
            'a budget paying for two runs keeps them apart' => [32, 2],
        ];
    }

    #[DataProvider('providerRunSizesTheLimitFromTheBudget')]
    public function testRunSizesTheLimitFromTheBudgetWhenNoneIsGiven(int $maxSteps, int $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $o = $a ? "ASC" : "DESC"; $sql = "BY $o";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $executor = new SliceExecutor(budget: new EvaluationBudget($maxSteps));

        $runs = $executor->run($steps, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertCount($expected, $runs);
    }

    public function testRunWithNoStepsHandsBackTheStart(): void
    {
        $start = new Environment(['a' => Domain::literal('x')]);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        self::assertSame([$start], (new SliceExecutor())->run([], $start, new FunctionScope('t.php'), $expressions));
    }

    public function testRunDropsRunsThatEndUpTheSame(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $o = $a ? "x" : "y"; $o = "z";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $runs = (new SliceExecutor())->run($steps, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame(['o=literal:string:z'], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
    }

    public function testRunJoinsTheRunsPastTheLimit(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = $p ? 1 : 2; $b = $q ? "x" : "y";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $runs = (new SliceExecutor())->run($steps, new Environment(), new FunctionScope('t.php'), $expressions, false, 3);

        self::assertSame([
            'a=literal:int:1;b=literal:string:x',
            'a=literal:int:1;b=literal:string:y',
            'combined;a=literal:int:2;b=literal:string:x|literal:string:y',
        ], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
    }

    public function testRunAbandonsTheStepsLeftOnceTheSearchBudgetIsExhausted(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $b = "y";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $budget = new EvaluationBudget(1);
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();

        $runs = (new SliceExecutor(budget: $budget))->run($steps, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame(['a=literal:string:x;maybe:b=opaque:mixed:budget'], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Budget, '$b')], $runs[0]->read('b')->terms);
    }

    public function testRunWhenClosingReadsOnPastTheSearchBudget(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $b = "y";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $budget = new EvaluationBudget(1);
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();

        $runs = (new SliceExecutor(budget: $budget))->run($steps, new Environment(), new FunctionScope('t.php'), $expressions, true);

        self::assertSame(['a=literal:string:x;b=literal:string:y'], array_map(static fn (Environment $environment): string => $environment->signature(), $runs));
    }

    public function testRunWhenClosingStopsOnceTheReadingBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $b = "y";');
        $steps = array_values(array_map(
            static fn (Expr\Assign $node): SliceStep => new SliceStep($node),
            (new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class),
        ));
        $budget = new EvaluationBudget(0);
        $budget->spend();
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $start = new Environment(['keep' => Domain::literal('k')]);

        $runs = (new SliceExecutor(budget: $budget))->run($steps, $start, new FunctionScope('t.php'), $expressions, true);

        self::assertSame(
            ['keep=literal:string:k;maybe:a=opaque:mixed:budget;maybe:b=opaque:mixed:budget'],
            array_map(static fn (Environment $environment): string => $environment->signature(), $runs),
        );
        self::assertSame(['keep'], $start->names());
    }

    /**
     * @return array<string, array{int, list<SliceStep>, int}>
     */
    public static function providerAffordableRuns(): array
    {
        return [
            'a generous budget keeps the most runs apart' => [20000, [new SliceStep(null)], 12],
            'a budget paying for exactly the most runs' => [96, [new SliceStep(null)], 12],
            'a budget paying for one run more than the most' => [104, [new SliceStep(null)], 12],
            'a budget paying for one run less than the most' => [88, [new SliceStep(null)], 11],
            'no steps cost as much as one' => [80, [], 10],
            'every step adds to the cost' => [160, [new SliceStep(null), new SliceStep(null)], 10],
            'steps held as alternatives add to the cost' => [160, [SliceStep::either([[new SliceStep(null)]])], 10],
            'a budget short of one run still keeps one' => [15, [new SliceStep(null)], 1],
            'a budget paying for two runs' => [16, [new SliceStep(null)], 2],
            'an empty budget still keeps one' => [0, [new SliceStep(null)], 1],
        ];
    }

    /**
     * @param list<SliceStep> $steps
     */
    #[DataProvider('providerAffordableRuns')]
    public function testAffordableRunsIsWhatTheBudgetPaysForAlongTheSteps(int $maxSteps, array $steps, int $expected): void
    {
        self::assertSame($expected, (new SliceExecutor(budget: new EvaluationBudget($maxSteps)))->affordableRuns($steps));
    }

    /**
     * @return array<string, array{list<SliceStep>, int}>
     */
    public static function providerSize(): array
    {
        return [
            'no steps' => [[], 0],
            'plain steps' => [[new SliceStep(null), new SliceStep(null), new SliceStep(null)], 3],
            'a step of alternatives counts itself and each alternative' => [
                [SliceStep::either([[new SliceStep(null), new SliceStep(null)], [new SliceStep(null)]])],
                4,
            ],
            'alternatives nested in alternatives' => [
                [new SliceStep(null), SliceStep::either([[SliceStep::either([[new SliceStep(null)], [new SliceStep(null)]])]])],
                5,
            ],
        ];
    }

    /**
     * @param list<SliceStep> $steps
     */
    #[DataProvider('providerSize')]
    public function testSizeCountsTheStepsHeldAsAlternatives(array $steps, int $expected): void
    {
        self::assertSame($expected, (new SliceExecutor())->size($steps));
    }

    public function testAbandonLeavesOpenEveryNameTheStepsLeftWrite(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $b = 2; $c .= 3;');
        $assignments = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class));
        $append = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\AssignOp\Concat::class);
        self::assertInstanceOf(Expr\AssignOp\Concat::class, $append);
        $steps = [
            new SliceStep($assignments[0]),
            SliceStep::either([[new SliceStep($assignments[1])], [new SliceStep($append)]]),
        ];
        $first = new Environment(['a' => Domain::literal('old'), 'keep' => Domain::literal('k')]);
        $second = new Environment();

        $open = (new SliceExecutor())->abandon($steps, [$first, $second]);

        self::assertSame([
            'keep=literal:string:k;maybe:a=opaque:mixed:budget;maybe:b=opaque:mixed:budget;maybe:c=opaque:mixed:budget',
            'maybe:a=opaque:mixed:budget;maybe:b=opaque:mixed:budget;maybe:c=opaque:mixed:budget',
        ], array_map(static fn (Environment $environment): string => $environment->signature(), $open));
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Budget, '$c')], $open[1]->read('c')->terms);
        self::assertNotSame($first, $open[0]);
        self::assertNotSame($second, $open[1]);
        self::assertSame('a=literal:string:old;keep=literal:string:k', $first->signature());
        self::assertSame([], $second->names());
    }

    public function testAbandonWithNoEnvironmentsLeavesNone(): void
    {
        self::assertSame([], (new SliceExecutor())->abandon([new SliceStep(new Expr\Variable('a'))], []));
    }

    public function testWrittenByANodeNamesWhatItAssignsThenTheNamesItLists(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1;');
        $assignment = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\Assign::class);
        self::assertInstanceOf(Expr\Assign::class, $assignment);

        self::assertSame(['a' => true, 'z' => true], (new SliceExecutor())->writtenBy(new SliceStep($assignment, [], ['z'])));
    }

    public function testWrittenByAForeachNamesItsValueAndItsKey(): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $k => $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);

        self::assertSame(['v' => true, 'k' => true], (new SliceExecutor())->writtenBy(new SliceStep($loop)));
    }

    public function testWrittenByAClosureNamesWhatItDefinesForItself(): void
    {
        $closure = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php fn ($id) => $id . $t;')->statements,
            Expr\ArrowFunction::class,
        );
        self::assertInstanceOf(Expr\ArrowFunction::class, $closure);

        self::assertSame(['id' => true], (new SliceExecutor())->writtenBy(new SliceStep($closure, [], ['id'])));
    }

    public function testWrittenByAStepOfAlternativesNamesWhatEveryAlternativeWrites(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $b = 2; $c = 3; $a = 4;');
        $assignments = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class));
        $step = SliceStep::either([
            [new SliceStep($assignments[0]), new SliceStep($assignments[1])],
            [SliceStep::either([[new SliceStep($assignments[2])], [new SliceStep($assignments[3])]])],
        ]);

        self::assertSame(['a' => true, 'b' => true, 'c' => true], (new SliceExecutor())->writtenBy($step));
    }

    public function testWrittenByAStepWithNoAlternativesNamesNothing(): void
    {
        self::assertSame([], (new SliceExecutor())->writtenBy(SliceStep::either([])));
    }

    public function testApplyRunsEveryAlternativeFromItsOwnCopy(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $t = "b"; $t = "c";');
        $assignments = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\Assign::class));
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['t' => Domain::literal('a')]);
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor())->apply(
            SliceStep::either([[], [new SliceStep($assignments[0])], [new SliceStep($assignments[1])]]),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame(
            ['t=literal:string:a', 't=literal:string:b', 't=literal:string:c'],
            array_map(static fn (Environment $run): string => $run->signature(), $runs),
        );
        self::assertNotSame($environment, $runs[0]);
        self::assertSame('t=literal:string:a', $environment->signature());
    }

    public function testApplyEntersAClosureWithoutEvaluatingAnything(): void
    {
        $closure = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php function (int $id) use ($t) { return $id; };')->statements,
            Expr\Closure::class,
        );
        self::assertInstanceOf(Expr\Closure::class, $closure);
        $budget = new EvaluationBudget();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $environment = new Environment(['t' => Domain::literal('users')]);
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor(budget: $budget))->apply(
            new SliceStep($closure, [], ['id']),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame(
            ['id=opaque:int:parameter;t=literal:string:users'],
            array_map(static fn (Environment $run): string => $run->signature(), $runs),
        );
        self::assertSame(0, $budget->spent());
        self::assertSame(['t'], $environment->names());
    }

    public function testApplyEntersAnArrowFunction(): void
    {
        $closure = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php fn (string $s) => $s;')->statements,
            Expr\ArrowFunction::class,
        );
        self::assertInstanceOf(Expr\ArrowFunction::class, $closure);
        $budget = new EvaluationBudget();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor(budget: $budget))->apply(
            new SliceStep($closure, [], ['s']),
            new Environment(),
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame(['s=opaque:string:parameter'], array_map(static fn (Environment $run): string => $run->signature(), $runs));
        self::assertSame(0, $budget->spent());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerApplyBindsADeclaration(): array
    {
        return [
            'global' => ['<?php global $db;', 'a=literal:string:x;db=object:PDO::'],
            'static' => ['<?php static $cache;', 'a=literal:string:x;cache=opaque:mixed:unresolved'],
            'unset' => ['<?php unset($a);', 'absent:a=literal:null:'],
        ];
    }

    #[DataProvider('providerApplyBindsADeclaration')]
    public function testApplyBindsADeclaration(string $code, string $expected): void
    {
        $declaration = (new SourceParser())->parse('t.php', $code)->statements[0];
        $budget = new EvaluationBudget();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $environment = new Environment(['a' => Domain::literal('x')]);
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor(new DeclaredGlobals(['db' => 'PDO']), budget: $budget))->apply(
            new SliceStep($declaration),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame([$expected], array_map(static fn (Environment $run): string => $run->signature(), $runs));
        self::assertSame('a=literal:string:x', $environment->signature());
        self::assertSame(0, $budget->spent());
    }

    public function testApplyStartsAForeachByReadingWhatItGoesOver(): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $k => $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $xs = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a')), new ArrayEntry(null, Domain::literal('b'))]));
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor())->apply(
            new SliceStep($loop),
            new Environment(['xs' => $xs]),
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame(['literal:int:0|literal:string:a'], array_map(
            static fn (Environment $run): string => $run->read('k')->signature() . '|' . $run->read('v')->signature(),
            $runs,
        ));
        self::assertSame(1, $passes[$loop]);
        self::assertSame($xs, $iterables[$loop]);
    }

    public function testApplyGoesOnFromThePassAForeachReachedOverWhatItFirstRead(): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $k => $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $budget = new EvaluationBudget();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $first = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a')), new ArrayEntry(null, Domain::literal('b'))]));
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        $passes[$loop] = 1;
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();
        $iterables[$loop] = $first;

        $runs = (new SliceExecutor(budget: $budget))->apply(
            new SliceStep($loop),
            new Environment(['xs' => Domain::literal('changed')]),
            new FunctionScope('t.php'),
            $expressions,
            $passes,
            $iterables,
        );

        self::assertSame(['literal:int:1|literal:string:b'], array_map(
            static fn (Environment $run): string => $run->read('k')->signature() . '|' . $run->read('v')->signature(),
            $runs,
        ));
        self::assertSame(2, $passes[$loop]);
        self::assertSame($first, $iterables[$loop]);
        self::assertSame(0, $budget->spent());
    }

    /**
     * @return array<string, array{int, list<string>}>
     */
    public static function providerApplySplitsAForeachPastItsElements(): array
    {
        return [
            'one run per element' => [12, ['literal:string:a', 'literal:string:b']],
            'one run holding every element' => [1, ['literal:string:a|literal:string:b']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerApplySplitsAForeachPastItsElements')]
    public function testApplySplitsAForeachPastItsElementsWithinTheLimit(int $limit, array $expected): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        $passes[$loop] = 2;
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();
        $iterables[$loop] = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a')), new ArrayEntry(null, Domain::literal('b'))]));

        $runs = (new SliceExecutor())->apply(new SliceStep($loop), new Environment(), new FunctionScope('t.php'), $expressions, $passes, $iterables, $limit);

        self::assertSame($expected, array_map(static fn (Environment $run): string => $run->read('v')->signature(), $runs));
    }

    /**
     * @return array<string, array{int, list<string>}>
     */
    public static function providerApplyAssignsAnExpression(): array
    {
        return [
            'one run per value' => [2, ['o=literal:string:x', 'o=literal:string:y']],
            'one run holding every value' => [1, ['o=literal:string:x|literal:string:y']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerApplyAssignsAnExpression')]
    public function testApplyAssignsAnExpressionAndSplitsOnWhatItWrote(int $limit, array $expected): void
    {
        $assignment = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php $o = $a ? "x" : "y";')->statements,
            Expr\Assign::class,
        );
        self::assertInstanceOf(Expr\Assign::class, $assignment);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor())->apply(new SliceStep($assignment), $environment, new FunctionScope('t.php'), $expressions, $passes, $iterables, $limit);

        self::assertSame($expected, array_map(static fn (Environment $run): string => $run->signature(), $runs));
        self::assertSame([], $environment->names());
    }

    public function testApplyLeavesAnyOtherStatementAsItFoundIt(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php echo $a = 1;')->statements[0];
        $budget = new EvaluationBudget();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $environment = new Environment(['a' => Domain::literal('x')]);
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();

        $runs = (new SliceExecutor(budget: $budget))->apply(new SliceStep($statement), $environment, new FunctionScope('t.php'), $expressions, $passes, $iterables);

        self::assertSame(['a=literal:string:x'], array_map(static fn (Environment $run): string => $run->signature(), $runs));
        self::assertNotSame($environment, $runs[0]);
        self::assertSame(0, $budget->spent());
    }

    public function testEnterClosureBindsParametersAndMarksOtherLocalsAbsent(): void
    {
        $closure = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php function (int $id, $name, ?string $unlisted) use ($t) {};')->statements,
            Expr\Closure::class,
        );
        self::assertInstanceOf(Expr\Closure::class, $closure);
        $environment = new Environment();

        (new SliceExecutor())->enterClosure($closure, ['id', 'name', 'other'], $environment);

        self::assertSame(['id', 'name', 'other'], $environment->names());
        self::assertEquals([new OpaqueTerm(TypeShape::of(['int']), Origin::Parameter, '$id')], $environment->read('id')->terms);
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Parameter, '$name')], $environment->read('name')->terms);
        self::assertEquals(Domain::literal(null)->terms, $environment->read('other')->terms);
    }

    public function testEnterClosureOfAnArrowFunctionReadsItsParameterTypes(): void
    {
        $closure = (new NodeFinder())->findFirstInstanceOf(
            (new SourceParser())->parse('t.php', '<?php fn (?string $s) => $s;')->statements,
            Expr\ArrowFunction::class,
        );
        self::assertInstanceOf(Expr\ArrowFunction::class, $closure);
        $environment = new Environment();

        (new SliceExecutor())->enterClosure($closure, ['s'], $environment);

        self::assertEquals([new OpaqueTerm(TypeShape::of(['string', 'null']), Origin::Parameter, '$s')], $environment->read('s')->terms);
    }

    public function testEnterClosureMarksNamesWithoutAParameterAbsent(): void
    {
        $closure = new Expr\Closure(['params' => [new Node\Param(new Expr\Variable(new Expr\Variable('n')), null, new Node\Identifier('int'))]]);
        $environment = new Environment();

        (new SliceExecutor())->enterClosure($closure, ['n'], $environment);

        self::assertEquals(Domain::literal(null)->terms, $environment->read('n')->terms);
    }

    public function testDeclareUnsetClearsOnlyThePlainVariablesItNames(): void
    {
        $declaration = (new SourceParser())->parse('t.php', '<?php unset($a, $b["k"], $$c, $d);')->statements[0];
        self::assertInstanceOf(Stmt\Unset_::class, $declaration);
        $environment = new Environment(['a' => Domain::literal('x'), 'b' => Domain::literal('y'), 'c' => Domain::literal('z')]);

        (new SliceExecutor())->declare($declaration, $environment);

        self::assertSame('absent:a=literal:null:;absent:d=literal:null:;b=literal:string:y;c=literal:string:z', $environment->signature());
    }

    public function testDeclareGlobalTakesTheClassTheNameIsKnownToHold(): void
    {
        $declaration = (new SourceParser())->parse('t.php', '<?php global $db, $other;')->statements[0];
        self::assertInstanceOf(Stmt\Global_::class, $declaration);
        $environment = new Environment();

        (new SliceExecutor(new DeclaredGlobals(['db' => 'PDO'])))->declare($declaration, $environment);

        self::assertEquals([new ObjectTerm('PDO')], $environment->read('db')->terms);
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Unresolved, 'global $other')], $environment->read('other')->terms);
    }

    public function testDeclareStaticNeverTakesAClass(): void
    {
        $declaration = (new SourceParser())->parse('t.php', '<?php static $db, $cache = [];')->statements[0];
        self::assertInstanceOf(Stmt\Static_::class, $declaration);
        $environment = new Environment();

        (new SliceExecutor(new DeclaredGlobals(['db' => 'PDO'])))->declare($declaration, $environment);

        self::assertSame(['db', 'cache'], $environment->names());
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Unresolved, 'static $db')], $environment->read('db')->terms);
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Unresolved, 'static $cache')], $environment->read('cache')->terms);
    }

    /**
     * @return array<string, array{Domain, int, string}>
     */
    public static function providerIterate(): array
    {
        $written = Domain::of(new ArrayTerm([
            new ArrayEntry(Domain::literal('first'), Domain::literal('a')),
            new ArrayEntry(null, Domain::literal('b')),
        ]));

        return [
            'an element written with a key takes it' => [$written, 0, 'k=literal:string:first;v=literal:string:a'],
            'an element written without a key takes the pass' => [$written, 1, 'k=literal:int:1;v=literal:string:b'],
            'a pass past the elements takes any element' => [$written, 2, 'k=opaque:int|string:loop;v=literal:string:a|literal:string:b'],
            'what is not an array gives any value' => [Domain::literal('text'), 0, 'k=opaque:int|string:loop;v=opaque:mixed:loop'],
        ];
    }

    #[DataProvider('providerIterate')]
    public function testIterateBindsTheValueAndKeyOfOnePass(Domain $iterable, int $pass, string $expected): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $k => $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        (new SliceExecutor())->iterate($loop, $iterable, $pass, $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame($expected, $environment->signature());
    }

    public function testIterateOverAnythingElseQuotesTheIteratedKeyAndValue(): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $k => $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        (new SliceExecutor())->iterate($loop, Domain::unknown(), 0, $environment, new FunctionScope('t.php'), $expressions);

        self::assertEquals([new OpaqueTerm(TypeShape::of(['int', 'string']), Origin::Loop, 'iterated key')], $environment->read('k')->terms);
        self::assertEquals([new OpaqueTerm(TypeShape::unknown(), Origin::Loop, 'iterated value')], $environment->read('v')->terms);
    }

    public function testIterateWithoutAKeyBindsOnlyTheValue(): void
    {
        $loop = (new SourceParser())->parse('t.php', '<?php foreach ($xs as $v) {}')->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        (new SliceExecutor())->iterate(
            $loop,
            Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a'))])),
            0,
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertSame('v=literal:string:a', $environment->signature());
    }

    /**
     * @return array<string, array{Domain, string}>
     */
    public static function providerAnyElement(): array
    {
        return [
            'what is not an array' => [Domain::literal('x'), 'opaque:mixed:loop'],
            'more than one array' => [
                Domain::fromTerms([
                    new ArrayTerm([new ArrayEntry(null, Domain::literal('a'))]),
                    new ArrayTerm([new ArrayEntry(null, Domain::literal('b'))]),
                ]),
                'opaque:mixed:loop',
            ],
            'an empty array' => [Domain::of(new ArrayTerm([])), 'opaque:mixed:loop'],
            'an array of one element' => [Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a'))])), 'literal:string:a'],
            'an array written out in full' => [
                Domain::of(new ArrayTerm([
                    new ArrayEntry(null, Domain::literal('a')),
                    new ArrayEntry(null, Domain::literal('b')),
                    new ArrayEntry(null, Domain::literal('c')),
                    new ArrayEntry(null, Domain::literal('a')),
                ])),
                'literal:string:a|literal:string:b|literal:string:c',
            ],
            'an array known only in part' => [
                Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a')), new ArrayEntry(null, Domain::literal('b'))], false)),
                'literal:string:a|literal:string:b|opaque:mixed:loop',
            ],
        ];
    }

    #[DataProvider('providerAnyElement')]
    public function testAnyElementIsWhateverAnElementCanBe(Domain $iterable, string $expected): void
    {
        self::assertSame($expected, (new SliceExecutor())->anyElement($iterable)->signature());
    }

    public function testAnyElementOfAnArrayKnownOnlyInPartQuotesTheIteratedValue(): void
    {
        $element = (new SliceExecutor())->anyElement(Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a'))], false)));

        self::assertEquals(new OpaqueTerm(TypeShape::unknown(), Origin::Loop, 'iterated value'), $element->terms[1]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerAssign(): array
    {
        return [
            'an assignment the evaluator writes' => ['$i = "b"', 'i=literal:string:b'],
            'an append the evaluator writes' => ['$i .= "b"', 'i=literal:string:ab'],
            'a post-increment' => ['$i++', 'i=opaque:int:unresolved'],
            'a pre-increment' => ['++$i', 'i=opaque:int:unresolved'],
            'a post-decrement' => ['$i--', 'i=opaque:int:unresolved'],
            'a pre-decrement' => ['--$i', 'i=opaque:int:unresolved'],
            'an arithmetic assignment' => ['$i += 1', 'i=opaque:mixed:unresolved'],
            'a reference assignment' => ['$r =& $i', 'maybe:i=opaque:mixed:unresolved;r=opaque:mixed:unresolved'],
            'an expression that assigns nothing' => ['f($i)', 'maybe:i=opaque:mixed:unresolved'],
        ];
    }

    #[DataProvider('providerAssign')]
    public function testAssignRunsEveryKindOfAssignment(string $code, string $expected): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php ' . $code . ';')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['i' => Domain::literal('a')]);

        (new SliceExecutor())->assign($statement->expr, $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame($expected, $environment->signature());
    }

    /**
     * @return array<string, array{string, string, OpaqueTerm}>
     */
    public static function providerAssignQuotes(): array
    {
        return [
            'a post-increment' => ['$i++', 'i', new OpaqueTerm(TypeShape::of(['int']), Origin::Unresolved, '$i++')],
            'a pre-decrement' => ['--$i', 'i', new OpaqueTerm(TypeShape::of(['int']), Origin::Unresolved, '--$i')],
            'an arithmetic assignment' => ['$i .= 1; $i *= 2', 'i', new OpaqueTerm(TypeShape::unknown(), Origin::Unresolved, '$i *= 2')],
        ];
    }

    #[DataProvider('providerAssignQuotes')]
    public function testAssignQuotesWhatItCouldNotFollow(string $code, string $name, OpaqueTerm $expected): void
    {
        $statements = (new SourceParser())->parse('t.php', '<?php ' . $code . ';')->statements;
        $statement = end($statements);
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        (new SliceExecutor())->assign($statement->expr, $environment, new FunctionScope('t.php'), $expressions);

        self::assertEquals([$expected], $environment->read($name)->terms);
    }

    /**
     * @return array<string, array{string, string, OpaqueTerm}>
     */
    public static function providerAssignOnceTheBudgetIsSpent(): array
    {
        return [
            'a reference takes what the evaluator gave' => ['$r =& $i', 'r', new OpaqueTerm(TypeShape::unknown(), Origin::Budget, 'budget exhausted')],
            'an increment quotes itself' => ['$i++', 'i', new OpaqueTerm(TypeShape::unknown(), Origin::Unresolved, '$i++')],
        ];
    }

    #[DataProvider('providerAssignOnceTheBudgetIsSpent')]
    public function testAssignOnceTheBudgetIsSpent(string $code, string $name, OpaqueTerm $expected): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php ' . $code . ';')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), [], new EvaluationBudget(0)))->evaluatorFor();
        $environment = new Environment();

        (new SliceExecutor())->assign($statement->expr, $environment, new FunctionScope('t.php'), $expressions);

        self::assertEquals([$expected], $environment->read($name)->terms);
    }

    public function testSplitWritesOneEnvironmentPerValue(): void
    {
        $environment = new Environment(['o' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')]), 'keep' => Domain::literal('k')]);

        $split = (new SliceExecutor())->split($environment, ['o' => true], 2);

        self::assertSame(
            ['keep=literal:string:k;o=literal:string:x', 'keep=literal:string:k;o=literal:string:y'],
            array_map(static fn (Environment $one): string => $one->signature(), $split),
        );
        self::assertNotSame($environment, $split[0]);
        self::assertSame('keep=literal:string:k;o=literal:string:x|literal:string:y', $environment->signature());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function providerSplitBelowTwo(): array
    {
        return [
            'a limit of one' => [1],
            'a limit of none' => [0],
        ];
    }

    #[DataProvider('providerSplitBelowTwo')]
    public function testSplitWithALimitBelowTwoKeepsTheEnvironment(int $limit): void
    {
        $environment = new Environment(['o' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')])]);

        self::assertSame([$environment], (new SliceExecutor())->split($environment, ['o' => true], $limit));
    }

    /**
     * @return array<string, array{Domain}>
     */
    public static function providerSplitKeepsWhole(): array
    {
        return [
            'a single value' => [Domain::literal('x')],
            'a widened domain' => [Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')], true)],
        ];
    }

    #[DataProvider('providerSplitKeepsWhole')]
    public function testSplitKeepsTheEnvironmentWhenTheNameHasNoValuesToTellApart(Domain $domain): void
    {
        $environment = new Environment(['o' => $domain]);

        self::assertSame([$environment], (new SliceExecutor())->split($environment, ['o' => true]));
    }

    public function testSplitSkipsANameTheEnvironmentDoesNotBind(): void
    {
        $environment = new Environment(['o' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')])]);

        $split = (new SliceExecutor())->split($environment, ['missing' => true, 'o' => true]);

        self::assertSame(['o=literal:string:x', 'o=literal:string:y'], array_map(static fn (Environment $one): string => $one->signature(), $split));
    }

    public function testSplitGoesOnPastANameWithNoValuesToTellApart(): void
    {
        $environment = new Environment([
            'a' => Domain::literal('k'),
            'o' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')]),
        ]);

        $split = (new SliceExecutor())->split($environment, ['a' => true, 'o' => true]);

        self::assertSame(
            ['a=literal:string:k;o=literal:string:x', 'a=literal:string:k;o=literal:string:y'],
            array_map(static fn (Environment $one): string => $one->signature(), $split),
        );
    }

    public function testSplitPairsEveryValueOfEveryName(): void
    {
        $environment = new Environment([
            'a' => Domain::fromTerms([new LiteralTerm(1), new LiteralTerm(2)]),
            'b' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')]),
        ]);

        $split = (new SliceExecutor())->split($environment, ['a' => true, 'b' => true]);

        self::assertSame([
            'a=literal:int:1;b=literal:string:x',
            'a=literal:int:1;b=literal:string:y',
            'a=literal:int:2;b=literal:string:x',
            'a=literal:int:2;b=literal:string:y',
        ], array_map(static fn (Environment $one): string => $one->signature(), $split));
    }

    public function testSplitJoinsWhatGoesPastTheLimit(): void
    {
        $environment = new Environment([
            'a' => Domain::fromTerms([new LiteralTerm(1), new LiteralTerm(2)]),
            'b' => Domain::fromTerms([new LiteralTerm('x'), new LiteralTerm('y')]),
        ]);

        $split = (new SliceExecutor())->split($environment, ['a' => true, 'b' => true], 3);

        self::assertSame([
            'a=literal:int:1;b=literal:string:x',
            'a=literal:int:1;b=literal:string:y',
            'combined;a=literal:int:2;b=literal:string:x|literal:string:y',
        ], array_map(static fn (Environment $one): string => $one->signature(), $split));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testSplitBoundsIntermediateProductsWithoutLosingValues(): void
    {
        $previousLimit = ini_set('memory_limit', '128M');
        self::assertIsString($previousLimit);
        try {
            $names = array_fill_keys(array_map(static fn (int $index): string => 'v' . $index, range(0, 23)), true);
            $domain = Domain::literal('a')->union(Domain::literal('b'));
            $environment = new Environment(array_fill_keys(array_keys($names), $domain) + ['unchanged' => Domain::literal('kept')]);
            $environment->markAbsent('absent');
            $original = $environment->signature();

            $split = (new SliceExecutor())->split($environment, $names, 3);

            self::assertCount(3, $split);
            self::assertFalse($split[0]->combined);
            self::assertFalse($split[1]->combined);
            self::assertTrue($split[2]->combined);
            self::assertSame(
                array_fill(0, 24, $domain->signature()),
                array_map(static fn (string $name): string => $split[2]->read($name)->signature(), array_keys($names)),
            );
            self::assertSame(['kept', 'kept', 'kept'], array_map(static fn (Environment $run): mixed => $run->read('unchanged')->soleLiteral()?->value, $split));
            self::assertSame(
                array_fill(0, 3, \SqlCatalog\Core\Evaluation\Presence::Absent),
                array_map(static fn (Environment $run): \SqlCatalog\Core\Evaluation\Presence => $run->presence('absent'), $split),
            );
            self::assertSame($original, $environment->signature());
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    public function testBoundDropsRepeatsKeepingTheFirst(): void
    {
        $first = new Environment(['a' => Domain::literal(1)]);
        $repeat = new Environment(['a' => Domain::literal(1)]);
        $other = new Environment(['a' => Domain::literal(2)]);

        self::assertSame([$first, $other], (new SliceExecutor())->bound([$first, $repeat, $other], 2));
    }

    public function testBoundKeepsEveryEnvironmentUpToTheLimit(): void
    {
        $environments = [
            new Environment(['a' => Domain::literal(1)]),
            new Environment(['a' => Domain::literal(2)]),
            new Environment(['a' => Domain::literal(3)]),
        ];

        self::assertSame($environments, (new SliceExecutor())->bound($environments, 3));
    }

    public function testBoundJoinsTheEnvironmentsPastTheLimit(): void
    {
        $first = new Environment(['a' => Domain::literal(1)]);

        $bound = (new SliceExecutor())->bound([
            $first,
            new Environment(['a' => Domain::literal(2)]),
            new Environment(['a' => Domain::literal(3)]),
            new Environment(['b' => Domain::literal('x')]),
        ], 2);

        self::assertSame(
            ['a=literal:int:1', 'combined;maybe:a=literal:int:2|literal:int:3|opaque:mixed:unresolved;maybe:b=literal:string:x|opaque:mixed:unresolved'],
            array_map(static fn (Environment $one): string => $one->signature(), $bound),
        );
        self::assertSame($first, $bound[0]);
    }

    public function testBoundOfOneJoinsThemAll(): void
    {
        $bound = (new SliceExecutor())->bound([new Environment(['a' => Domain::literal(1)]), new Environment(['a' => Domain::literal(2)])], 1);

        self::assertSame(['combined;a=literal:int:1|literal:int:2'], array_map(static fn (Environment $one): string => $one->signature(), $bound));
    }

    public function testBoundKeepsTwelveApartByDefault(): void
    {
        $environments = array_map(static fn (int $value): Environment => new Environment(['a' => Domain::literal($value)]), range(1, 13));

        $bound = (new SliceExecutor())->bound($environments);

        self::assertCount(12, $bound);
        self::assertSame('combined;a=literal:int:12|literal:int:13', $bound[11]->signature());
    }

    public function testBoundOfNothingIsNothing(): void
    {
        self::assertSame([], (new SliceExecutor())->bound([]));
    }
}
