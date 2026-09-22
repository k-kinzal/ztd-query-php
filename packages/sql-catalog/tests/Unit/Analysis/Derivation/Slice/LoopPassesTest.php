<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Derivation\Slice\Arrival;
use SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\Derivation\Slice\BranchArms;
use SqlCatalog\Analysis\Derivation\Slice\LoopPasses;
use SqlCatalog\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Analysis\Derivation\SourceTree;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\SourceParser;

#[CoversClass(LoopPasses::class)]
#[UsesClass(AssignmentSteps::class)]
#[UsesClass(Arrival::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(BranchArms::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(SourceParser::class)]
final class LoopPassesTest extends TestCase
{
    public function testIsLoopRecognisesEveryKindOfLoop(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while (0) {} do {} while (0); for (;;) {} foreach ($a as $b) {} if (0) {}');
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()), new FreeNames(), new ModifiedNames(), new EvaluationBudget());

        self::assertSame([true, true, true, true, false], array_map(static fn (Stmt $statement): bool => $loops->isLoop($statement), $file->statements));
    }

    public function testRunsListTheStatementAfterEachNumberOfPassesUpToTheLimit(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach ($conds as $c) { $sql .= " AND x"; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        $runs = $loops->runs($file->statements[0], ['sql' => true]);

        self::assertSame([0, 1, 2], array_map(static fn (Pending $run): int => count($run->steps), $runs));
        self::assertTrue($runs[0]->truncated);
    }

    public function testRunsTakeExactlyAsManyPassesAsAWrittenOutArrayHasElements(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach (["a", "b"] as $c) { $sql .= $c; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        $runs = $loops->runs($file->statements[0], ['sql' => true]);

        self::assertCount(1, $runs);
        self::assertFalse($runs[0]->truncated);
    }

    public function testRunsStopOnceAPassCannotChangeWhatIsNeeded(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while ($more) { $sql = "SELECT 1"; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        $runs = $loops->runs($file->statements[0], ['sql' => true]);

        self::assertCount(2, $runs);
        self::assertFalse($runs[1]->truncated);
    }

    public function testRunsOfADoWhileAlwaysIncludeAPass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php do { $sql = "SELECT 1"; } while ($more);');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertCount(1, $loops->runs($file->statements[0], ['sql' => true]));
    }

    public function testLeaveBodyFollowsAPointInsideALoopBackThroughEarlierPasses(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "A"; foreach ($rows as $row) { q($sql . $row); $sql .= "B"; }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $budget = new EvaluationBudget();
        $loop = $file->statements[1];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        $arrivals = $loops->leaveBody($loop, [Pending::needing(['sql' => true, 'row' => true])]);

        self::assertCount(3, $arrivals);
        self::assertTrue($arrivals[2]->path->truncated);
        self::assertSame(['rows' => true], $arrivals[0]->path->needs);
    }

    public function testPassWalksBackOverOneWholePass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php for ($i = 0; $i < 3; $i++) { $sql .= $i; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        $first = $loops->pass($file->statements[0], [Pending::needing(['sql' => true])]);
        $second = $loops->pass($file->statements[0], $first);

        self::assertCount(1, $first[0]->steps);
        self::assertSame(['sql', 'i'], array_keys($first[0]->needs));
        self::assertCount(3, $second[0]->steps);
    }

    public function testBodyOfIsWhatALoopRepeats(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while (0) { $a = 1; } if (0) { $b = 1; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertCount(1, $loops->bodyOf($file->statements[0]));
        self::assertSame([], $loops->bodyOf($file->statements[1]));
    }

    public function testBindingIsAStepOnlyForAPathThatNeedsTheLoopsVariables(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach ($rows as $key => $row) {}');
        $loop = $file->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertSame(['other' => true], $loops->binding($loop, Pending::needing(['row' => true, 'other' => true]))->needs);
        self::assertSame([], $loops->binding($loop, Pending::needing(['other' => true]))->steps);
        self::assertSame([], $loops->binding($loop, Pending::needing([]))->steps);
    }

    public function testEnteringReadsWhatALoopGoesOverBeforeItsFirstPass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach ($rows as $row) {} for ($i = $start; ;) {} while (0) {}');
        $foreach = $file->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $foreach);
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);
        $bound = $loops->binding($foreach, Pending::needing(['row' => true]));

        self::assertSame(['rows' => true], $loops->entering($foreach, [$bound])[0]->needs);
        self::assertSame(['x' => true], $loops->entering($foreach, [Pending::needing(['x' => true])])[0]->needs);
        self::assertSame(['start' => true], $loops->entering($file->statements[1], [Pending::needing(['i' => true])])[0]->needs);
        self::assertSame(['x' => true], $loops->entering($file->statements[2], [Pending::needing(['x' => true])])[0]->needs);
    }

    public function testBoundSaysWhetherAPathTookOneOfTheLoopsPasses(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach ($rows as $row) {}');
        $loop = $file->statements[0];
        self::assertInstanceOf(Stmt\Foreach_::class, $loop);
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertTrue($loops->bound($loop, new Pending([new SliceStep($loop)], [])));
        self::assertFalse($loops->bound($loop, Pending::needing([])));
    }

    public function testExpressionsWalkBackOverAListLastFirst(): void
    {
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree(), $budget), new FreeNames(), new ModifiedNames(), $budget);
        $first = new Expr\Assign(new Expr\Variable('a'), new Expr\Variable('b'));
        $second = new Expr\Assign(new Expr\Variable('b'), new Expr\Variable('c'));

        $paths = $loops->expressions([$first, $second], [Pending::needing(['a' => true]), Pending::needing([])]);

        self::assertSame(['b' => true], $paths[0]->needs);
        self::assertSame([], $paths[1]->steps);
    }

    public function testContinuesSaysWhetherAnotherPassCouldChangeWhatIsNeeded(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while (0) { $a = 1; }');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertTrue($loops->continues($file->statements[0], [Pending::needing(['a' => true])]));
        self::assertFalse($loops->continues($file->statements[0], [Pending::needing(['b' => true]), Pending::needing([])]));
    }

    public function testWrittenPassesCountsOnlyAnArrayWrittenOutInFull(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach ([1, 2, 3] as $a) {} foreach ([...$b] as $a) {} foreach ($c as $a) {} while (0) {}');
        $budget = new EvaluationBudget();
        $loops = new LoopPasses(new BackwardSlicer(new SourceTree([$file]), $budget), new FreeNames(), new ModifiedNames(), $budget);

        self::assertSame([3, null, null, null], array_map(static fn (Stmt $loop): ?int => $loops->writtenPasses($loop), $file->statements));
    }
}
