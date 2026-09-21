<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

#[CoversClass(BackwardSlicer::class)]
#[UsesClass(Arrival::class)]
#[UsesClass(AssignmentSteps::class)]
#[UsesClass(BranchArms::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(LoopPasses::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(SourceParser::class)]
final class BackwardSlicerTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function providerSliceFrom(): array
    {
        return [
            'keeps only what the argument depends on' => [
                '$t = "users"; $unused = 1; $sql = "SELECT * FROM $t"; q($sql);',
                ['$t = "users"', '$sql = "SELECT * FROM {$t}"'],
                [],
            ],
            'looks for what an append adds to' => [
                '$sql = "SELECT 1"; $sql .= " FROM t"; q($sql);',
                ['$sql = "SELECT 1"', '$sql .= " FROM t"'],
                [],
            ],
            'leaves a parameter to be found at the start' => [
                '$sql = "SELECT * FROM " . $table; q($sql);',
                ['$sql = "SELECT * FROM " . $table'],
                ['table'],
            ],
            'steps over a branch that assigns nothing it needs' => [
                'if ($a) { $x = 1; } $sql = "SELECT 1"; q($sql);',
                ['$sql = "SELECT 1"'],
                [],
            ],
            'holds the arms of a branch that does as alternatives' => [
                'if ($a) { $t = "a"; } else { $t = "b"; } q($t);',
                ['either'],
                [],
            ],
        ];
    }

    /**
     * @param list<string> $steps
     * @param list<string> $needs
     */
    #[DataProvider('providerSliceFrom')]
    public function testSliceFromKeepsWhatTheCallDependsOnInTheOrderItRuns(string $body, array $steps, array $needs): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($table, $a) { ' . $body . ' }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $printer = new Standard();

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))
            ->sliceFrom($call, (new FreeNames())->of([$call->getArgs()[0]->value]));

        self::assertCount(1, $arrivals);
        self::assertSame($file->statements[0], $arrivals[0]->body);
        self::assertSame($steps, array_map(
            static fn (SliceStep $step): string => $step->node instanceof Expr ? $printer->prettyPrintExpr($step->node) : 'either',
            $arrivals[0]->path->forward(),
        ));
        self::assertSame($needs, array_keys($arrivals[0]->path->needs));
    }

    public function testSliceFromAnArrowFunctionTakesWhatItCapturesFromWhereItIsWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $t = "users"; return fn ($id) => q("SELECT * FROM $t WHERE id = $id"); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))
            ->sliceFrom($call, (new FreeNames())->of([$call->getArgs()[0]->value]));

        $steps = $arrivals[0]->path->forward();
        self::assertCount(2, $steps);
        self::assertInstanceOf(Expr\Assign::class, $steps[0]->node);
        self::assertSame(['id'], $steps[1]->names);
        self::assertSame([], $arrivals[0]->path->needs);
    }

    public function testSliceFromANodeOutsideAnyStatementArrivesAtNothing(): void
    {
        $arrivals = (new BackwardSlicer(new SourceTree(), new EvaluationBudget()))->sliceFrom(new Expr\Variable('a'), ['a' => true]);

        self::assertNull($arrivals[0]->body);
        self::assertSame(['a' => true], $arrivals[0]->path->needs);
    }

    public function testSliceFromEndWalksBackFromTheEndOfABody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $this->from = " FROM t"; $this->from .= " JOIN u"; }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))->sliceFromEnd($function, ['this->from' => true]);

        self::assertCount(2, $arrivals[0]->path->steps);
        self::assertSame([], $arrivals[0]->path->needs);
    }

    public function testBeforeWalksBackOverTheStatementsAheadOfOne(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $b = 2;');

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))->before($file->statements[1], [Pending::needing(['a' => true])]);

        self::assertSame([], $arrivals[0]->path->needs);
    }

    public function testBeforeAStatementNotInAnyListArrivesWhereItIs(): void
    {
        $arrivals = (new BackwardSlicer(new SourceTree(), new EvaluationBudget()))->before(new Stmt\Nop(), [Pending::needing(['a' => true])]);

        self::assertNull($arrivals[0]->body);
    }

    public function testLeaveCarriesPathsOutOfTheirOwner(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; function f() {} class C { function m() {} }');
        $namespace = $file->statements[0];
        self::assertInstanceOf(Stmt\Namespace_::class, $namespace);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $slicer = new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget());
        $paths = [Pending::needing(['x' => true])];

        self::assertNull($slicer->leave($namespace, $paths)[0]->body);
        self::assertSame($method, $slicer->leave($method, $paths)[0]->body);
        self::assertNull($slicer->leave(null, $paths)[0]->body);
    }

    public function testLeaveCarriesAFinallyBlockOverItsTryBlock(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php try { $a = 1; } finally { q($a); }');
        $try = $file->statements[0];
        self::assertInstanceOf(Stmt\TryCatch::class, $try);
        self::assertNotNull($try->finally);

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))->leave($try->finally, [Pending::needing(['a' => true])]);

        self::assertSame([], $arrivals[0]->path->needs);
    }

    public function testOwningStatementIsTheStatementAnArmBelongsTo(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($a) {} else {}');
        $if = $file->statements[0];
        self::assertInstanceOf(Stmt\If_::class, $if);
        self::assertNotNull($if->else);
        $slicer = new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget());

        self::assertSame($if, $slicer->owningStatement($if->else));
        self::assertSame($if, $slicer->owningStatement($if));
        self::assertNull($slicer->owningStatement(new Expr\Variable('a')));
    }

    public function testLeaveClosureTakesOnlyTheUseListFromOutside(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $t = "u"; $x = "v"; $g = function ($id) use ($t) { return $t . $id . $x; }; }');
        $closure = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\Closure::class);
        self::assertInstanceOf(Expr\Closure::class, $closure);

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))
            ->leaveClosure($closure, [Pending::needing(['t' => true, 'id' => true, 'x' => true])]);

        self::assertSame(['id', 'x'], $arrivals[0]->path->forward()[1]->names);
        self::assertSame([], $arrivals[0]->path->needs);
    }

    public function testOutsideIsWhatAClosureTakesFromWhereItIsWritten(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function ($a) use ($b) {}; fn ($a) => $b;');
        $closure = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\Closure::class);
        $arrow = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\ArrowFunction::class);
        self::assertInstanceOf(Expr\Closure::class, $closure);
        self::assertInstanceOf(Expr\ArrowFunction::class, $arrow);
        $slicer = new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget());
        $needs = ['a' => true, 'b' => true, 'c' => true, 'this' => true];

        self::assertSame(['b', 'this'], array_keys($slicer->outside($closure, $needs)));
        self::assertSame(['b', 'c', 'this'], array_keys($slicer->outside($arrow, $needs)));
    }

    public function testArriveNamesTheNamedBodyAClosureIsWrittenIn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $g = function () {}; }');
        $closure = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\Closure::class);
        self::assertInstanceOf(Expr\Closure::class, $closure);

        $arrivals = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))->arrive($closure, [Pending::needing([])]);

        self::assertSame($file->statements[0], $arrivals[0]->body);
    }

    public function testWalkListStopsOnceEveryPathIsDone(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $b = 2; $a = 3;');
        $budget = new EvaluationBudget();

        (new BackwardSlicer(new SourceTree([$file]), $budget))->walkList($file->statements, 3, [Pending::needing(['a' => true])]);

        self::assertSame(1, $budget->spent());
    }

    public function testAllDoneIsTrueOnlyWhenNoPathNeedsAnything(): void
    {
        $slicer = new BackwardSlicer(new SourceTree(), new EvaluationBudget());

        self::assertTrue($slicer->allDone([Pending::needing([])]));
        self::assertFalse($slicer->allDone([Pending::needing([]), Pending::needing(['a' => true])]));
    }

    public function testOverStopsAPathOnceTheBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1;');

        $paths = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget(0)))->over($file->statements[0], Pending::needing(['a' => true]));

        self::assertTrue($paths[0]->exhausted);
    }

    public function testOverWalksDeclarationsBlocksLoopsAndConditionAssignments(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php global $db; { $a = 1; } while (0) { $a = 2; } if ($b = f()) { $a = 3; }');
        $slicer = new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget());

        self::assertSame([], $slicer->over($file->statements[0], Pending::needing(['db' => true]))[0]->needs);
        self::assertSame([], $slicer->over($file->statements[1], Pending::needing(['a' => true]))[0]->needs);
        self::assertCount(1, $slicer->over($file->statements[2], Pending::needing(['a' => true])));
        self::assertSame(['a'], array_keys($slicer->over($file->statements[3], Pending::needing(['a' => true, 'b' => true]))[0]->needs));
    }

    public function testWalkArmsWalksEachArmFromItsEnd(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = 1; $a = 2;');

        $runs = (new BackwardSlicer(new SourceTree([$file]), new EvaluationBudget()))->walkArms([[$file->statements[0]], [$file->statements[1]], []], ['a' => true]);

        self::assertSame([0, 0, 1], array_map(static fn (Pending $run): int => count($run->needs), $runs));
    }

    public function testBranchHoldsSeveralRunsAsOneStep(): void
    {
        $slicer = new BackwardSlicer(new SourceTree(), new EvaluationBudget());
        $path = Pending::needing(['a' => true]);
        $run = new Pending([new SliceStep(new Expr\Variable('x'))], []);

        self::assertSame([$path], $slicer->branch($path, []));
        self::assertCount(1, $slicer->branch($path, [$run])[0]->steps);
        self::assertSame([], $slicer->branch($path, [$run])[0]->steps[0]->alternatives);
        self::assertCount(2, $slicer->branch($path, [$run, $run])[0]->steps[0]->alternatives);
    }

    public function testBoundMergesRepeatsAndGathersPathsBeyondTheLimit(): void
    {
        $slicer = new BackwardSlicer(new SourceTree(), new EvaluationBudget());
        $paths = array_map(
            static fn (int $index): Pending => Pending::needing(['v' . $index => true]),
            range(1, BackwardSlicer::MAX_PATHS + 3),
        );

        self::assertCount(1, $slicer->bound([Pending::needing(['a' => true]), Pending::needing(['a' => true])]));
        self::assertCount(BackwardSlicer::MAX_PATHS, $slicer->bound($paths));
        self::assertCount(4, $slicer->bound($paths)[BackwardSlicer::MAX_PATHS - 1]->steps[0]->alternatives);
    }
}
