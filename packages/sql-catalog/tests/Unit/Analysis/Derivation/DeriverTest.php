<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Analysis\CallEvaluator;
use SqlCatalog\Analysis\ConstantReader;
use SqlCatalog\Analysis\Derivation\Binding;
use SqlCatalog\Analysis\Derivation\CalleeReturns;
use SqlCatalog\Analysis\Derivation\CallerIndex;
use SqlCatalog\Analysis\Derivation\Callers;
use SqlCatalog\Analysis\Derivation\Deriver;
use SqlCatalog\Analysis\Derivation\EntryBinder;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Derivation\PropertyWrites;
use SqlCatalog\Analysis\Derivation\Slice\Arrival;
use SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\Derivation\Slice\BranchArms;
use SqlCatalog\Analysis\Derivation\Slice\LoopPasses;
use SqlCatalog\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Analysis\Derivation\Solution;
use SqlCatalog\Analysis\Derivation\SourceTree;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\ExternalInput;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\ReferenceEvaluator;
use SqlCatalog\Analysis\SinkFinder;
use SqlCatalog\Analysis\SinkMatcher;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\ClassShape;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Deriver::class)]
#[UsesClass(Arrival::class)]
#[UsesClass(AssignmentSteps::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(Binding::class)]
#[UsesClass(BranchArms::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(CalleeReturns::class)]
#[UsesClass(CallerIndex::class)]
#[UsesClass(Callers::class)]
#[UsesClass(CallEvaluator::class)]
#[UsesClass(ClassShape::class)]
#[UsesClass(ConstantReader::class)]
#[UsesClass(DeclaredGlobals::class)]
#[UsesClass(Domain::class)]
#[UsesClass(EntryBinder::class)]
#[UsesClass(Environment::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(ExpressionEvaluator::class)]
#[UsesClass(ExternalInput::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(FunctionShape::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(LoopPasses::class)]
#[UsesClass(MethodShape::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(ParameterShape::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(Pending::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(PropertyWrites::class)]
#[UsesClass(ReferenceEvaluator::class)]
#[UsesClass(SinkFinder::class)]
#[UsesClass(SinkMatcher::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(Solution::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectMemory::class)]
final class DeriverTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function providerScopeOf(): array
    {
        return [
            'code at the top of a file' => ['<?php namespace N; q();', FunctionScope::MAIN, null],
            'a function' => ['<?php namespace N; function f() { q(); }', 'N\f', null],
            'a method' => ['<?php namespace N; class A { function m() { q(); } }', 'N\A::m', 'N\A'],
            'a method of a class outside every namespace' => ['<?php class A { function m() { q(); } }', 'A::m', 'A'],
            'a method of a trait' => ['<?php namespace N; trait T { function t() { q(); } }', 'N\T::t', 'N\T'],
            'a method of an enum' => ['<?php namespace N; enum E { case X; function e() { q(); } }', 'N\E::e', 'N\E'],
            'a method of an anonymous class' => ['<?php namespace N; class A { function m() { $o = new class { function k() { q(); } }; } }', '::k', null],
        ];
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function providerScopeOfAClosure(): array
    {
        return [
            'a closure in a method' => ['<?php namespace N; class A { function m() { $c = function () { q(); }; } }', 'N\A::m', 'N\A'],
            'a closure in a closure in a method' => ['<?php namespace N; class A { function m() { $c = function () { $n = function () { q(); }; }; } }', 'N\A::m', 'N\A'],
            'an arrow function in a function' => ['<?php namespace N; function f() { $g = fn () => q(); }', 'N\f', null],
            'an arrow function in a closure in a function' => ['<?php namespace N; function f() { $c = function () { $g = fn () => q(); }; }', 'N\f', null],
            'a closure at the top of a file' => ['<?php namespace N; $c = function () { q(); };', FunctionScope::MAIN, null],
        ];
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function providerClassOf(): array
    {
        return [
            'a class in a namespace' => ['<?php namespace N; class A { function m() { q(); } }', 'N\A'],
            'a class outside every namespace' => ['<?php class A { function m() { q(); } }', 'A'],
            'a trait' => ['<?php namespace N; trait T { function t() { q(); } }', 'N\T'],
            'an interface' => ['<?php namespace N; interface I { const C = q(); }', 'N\I'],
            'an enum' => ['<?php namespace N; enum E { case X; function e() { q(); } }', 'N\E'],
            'a function' => ['<?php namespace N; function f() { q(); }', null],
            'the top of a file' => ['<?php namespace N; q();', null],
            'an anonymous class in a class' => ['<?php namespace N; class A { function m() { $o = new class { function k() { q(); } }; } }', null],
        ];
    }

    /**
     * @return array<string, array{string, class-string<Node\FunctionLike>, string}>
     */
    public static function providerNameOf(): array
    {
        return [
            'a method, by its class' => ['<?php namespace N; class A { function m() {} }', Stmt\ClassMethod::class, 'N\A::m'],
            'a function, by its namespaced name' => ['<?php namespace N; function f() {}', Stmt\Function_::class, 'N\f'],
            'a closure, by the class it is written in' => ['<?php namespace N; class A { function m() { $c = function () {}; } }', Expr\Closure::class, 'N\A::{closure}'],
            'an arrow function, by the class it is written in' => ['<?php namespace N; class A { function m() { $c = fn () => 1; } }', Expr\ArrowFunction::class, 'N\A::{closure}'],
            'a closure outside every class, by the file' => ['<?php namespace N; function f() { $c = function () {}; }', Expr\Closure::class, '{main}::{closure}'],
            'a method of an anonymous class, by no class' => ['<?php namespace N; $o = new class { function k() {} };', Stmt\ClassMethod::class, '::k'],
        ];
    }

    public function testBinderIsTheEntryBinderTheDeriverWasWiredWith(): void
    {
        $budget = new EvaluationBudget();
        $tree = new SourceTree();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $binder = new EntryBinder(new Callers(new CallerIndex(), new ProgramIndex()), $expressions, $budget);

        $deriver = new Deriver($tree, new BackwardSlicer($tree, $budget), new SliceExecutor(), $expressions, $binder, $budget);

        self::assertSame($binder, $deriver->binder());
    }

    public function testEvaluatorIsTheEvaluatorTheDeriverWasWiredWith(): void
    {
        $budget = new EvaluationBudget();
        $tree = new SourceTree();
        $expressions = (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor();
        $binder = new EntryBinder(new Callers(new CallerIndex(), new ProgramIndex()), $expressions, $budget);

        $deriver = new Deriver($tree, new BackwardSlicer($tree, $budget), new SliceExecutor(), $expressions, $binder, $budget);

        self::assertSame($expressions, $deriver->evaluator());
    }

    public function testSolveGivesOneSolutionPerBranchWithCorrelatedValues(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(bool $admin): void { if ($admin) { $t = "admins"; $c = "admin_id"; } else { $t = "users"; $c = "user_id"; } q($c, $t); }',
        );
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value, $call->getArgs()[1]->value]);

        self::assertSame(
            [[['admin_id'], ['admins']], [['user_id'], ['users']]],
            array_map(static fn (Solution $solution): array => array_map(
                static fn (Domain $value): array => array_map(static fn (TextPattern $pattern): string => $pattern->display(), $value->patterns()),
                $solution->values,
            ), $solutions),
        );
        self::assertSame([['f'], ['f']], array_map(static fn (Solution $solution): array => $solution->through, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->combined, $solutions));
    }

    public function testSolveReadsAParameterAtEveryCallOfItsBody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); } f("a"); f("b");');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame(['a', 'b'], array_map(static fn (Solution $solution): string|int|float|bool|null => $solution->values[0]->soleLiteral()?->value, $solutions));
        self::assertSame([['{main}', 'f'], ['{main}', 'f']], array_map(static fn (Solution $solution): array => $solution->through, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
    }

    public function testSolveLeavesAParameterNothingPassesOpenAsAParameter(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertCount(1, $solutions);
        self::assertSame(['f'], $solutions[0]->through);
        self::assertSame(Origin::Parameter, $solutions[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('$t', $solutions[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testSolveLeavesAParameterOpenForTheBudgetOnceTheCallsAreTooDeep(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); } f("a");');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value], 4);

        self::assertCount(1, $solutions);
        self::assertSame(['f'], $solutions[0]->through);
        self::assertSame(Origin::Budget, $solutions[0]->values[0]->patterns()[0]->holes()[0]->origin);
    }

    public function testSolveRemembersWhatItFoundForTheSamePointGoalsAndDepth(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); } f("a");');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $first = $deriver->solve($call, [$call->getArgs()[0]->value], 1);
        $again = $deriver->solve($call, [$call->getArgs()[0]->value], 1);

        self::assertCount(1, $first);
        self::assertSame('a', $first[0]->values[0]->soleLiteral()?->value);
        self::assertSame($first, $again);
    }

    public function testSolveTellsPointsGoalsAndDepthsApart(): void
    {
        $sequence = (new SourceParser())->parse('t.php', '<?php $t = "a"; q1($t, "x"); $t = "b"; q2($t, "y");');
        $calls = (new NodeFinder())->findInstanceOf($sequence->statements, Expr\FuncCall::class);
        $sequenceDeriver = (new Interpreter((new ProgramIndexBuilder())->build([$sequence]), (new PdoExtension())->sinks()))->deriverFor([$sequence]);
        $called = (new SourceParser())->parse('u.php', '<?php function f($t) { q($t); } f("a");');
        $call = (new NodeFinder())->findFirst($called->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $calledDeriver = (new Interpreter((new ProgramIndexBuilder())->build([$called]), (new PdoExtension())->sinks()))->deriverFor([$called]);

        $atSecond = $sequenceDeriver->solve($calls[1], [$calls[1]->getArgs()[0]->value]);
        $atFirst = $sequenceDeriver->solve($calls[0], [$calls[1]->getArgs()[0]->value]);
        $otherGoal = $sequenceDeriver->solve($calls[1], [$calls[1]->getArgs()[1]->value]);
        $shallow = $calledDeriver->solve($call, [$call->getArgs()[0]->value]);
        $deep = $calledDeriver->solve($call, [$call->getArgs()[0]->value], 4);

        self::assertSame('b', $atSecond[0]->values[0]->soleLiteral()?->value);
        self::assertSame('a', $atFirst[0]->values[0]->soleLiteral()?->value);
        self::assertSame('y', $otherGoal[0]->values[0]->soleLiteral()?->value);
        self::assertSame('a', $shallow[0]->values[0]->soleLiteral()?->value);
        self::assertSame(Origin::Budget, $deep[0]->values[0]->patterns()[0]->holes()[0]->origin);
    }

    public function testSolveForgetsWhatItFoundOnceTheBudgetRanOut(): void
    {
        $budget = new EvaluationBudget(10);
        $file = (new SourceParser())->parse('t.php', '<?php $t = "a"; q($t);');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), $budget))->deriverFor([$file]);
        array_map(static fn (int $step): bool => $budget->spend(), range(0, 10));

        $starved = $deriver->solve($call, [$call->getArgs()[0]->value]);
        $budget->reset();
        $fed = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertCount(1, $starved);
        self::assertSame(Origin::Budget, $starved[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('$t', $starved[0]->values[0]->patterns()[0]->holes()[0]->expression);
        self::assertCount(1, $fed);
        self::assertSame('a', $fed[0]->values[0]->soleLiteral()?->value);
    }

    public function testSolveEvaluatesTheGoalsInTheScopeOfTheBodyThePointIsIn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace N; class R { const T = "users"; function m() { q(self::T, $this); } }');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value, $call->getArgs()[1]->value]);

        self::assertCount(1, $solutions);
        self::assertSame('users', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertSame('N\R', $solutions[0]->values[1]->soleObject()?->className);
        self::assertSame(['N\R::m'], $solutions[0]->through);
    }

    public function testSolveTakesACallOfTheBodyThePointIsInAsRecursion(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f() { $x = "x" . f(); q($x); return "y"; }');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertCount(1, $solutions);
        self::assertSame('x{$}', $solutions[0]->values[0]->patterns()[0]->display());
        self::assertSame(Origin::Budget, $solutions[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('f()', $solutions[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testSolveMarksEverySolutionTruncatedWhenCallsWereLeftOut(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($t) { q($t); } ' . implode(' ', array_map(static fn (int $caller): string => sprintf('f("a%d");', $caller), range(0, Deriver::MAX_CALLERS))),
        );
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame(
            array_map(static fn (int $caller): string => 'a' . $caller, range(0, Deriver::MAX_CALLERS - 1)),
            array_map(static fn (Solution $solution): string|int|float|bool|null => $solution->values[0]->soleLiteral()?->value, $solutions),
        );
        self::assertSame(array_fill(0, Deriver::MAX_CALLERS, true), array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
        self::assertSame(array_fill(0, Deriver::MAX_CALLERS, false), array_map(static fn (Solution $solution): bool => $solution->combined, $solutions));
    }

    public function testSolveMarksTheWaysInJoinedForWantOfBudgetAsCombined(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); } f("a"); f("b"); f("c");');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), new EvaluationBudget(16)))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame(
            [['a'], ['b', 'c']],
            array_map(static fn (Solution $solution): array => array_map(static fn (TextPattern $pattern): string => $pattern->display(), $solution->values[0]->patterns()), $solutions),
        );
        self::assertSame([false, true], array_map(static fn (Solution $solution): bool => $solution->combined, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
        self::assertSame([['{main}', 'f'], ['{main}', 'f']], array_map(static fn (Solution $solution): array => $solution->through, $solutions));
    }

    public function testSolveKeepsTheRunsOfEveryWayInApartWhileTheBudgetAffordsThem(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($t) { $o = g() ? "ASC" : "DESC"; q($t . $o); } ' . implode(' ', array_map(static fn (int $caller): string => sprintf('f("a%d ");', $caller), range(0, 5))),
        );
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame(
            [['a0 ASC'], ['a0 DESC'], ['a1 ASC'], ['a1 DESC'], ['a2 ASC'], ['a2 DESC'], ['a3 ASC'], ['a3 DESC'], ['a4 ASC'], ['a4 DESC'], ['a5 ASC'], ['a5 DESC']],
            array_map(static fn (Solution $solution): array => array_map(static fn (TextPattern $pattern): string => $pattern->display(), $solution->values[0]->patterns()), $solutions),
        );
    }

    public function testSolveSharesTheRunsTheBudgetAffordsAmongTheWaysIn(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($t) { $o = g() ? "ASC" : "DESC"; q($t . $o); } ' . implode(' ', array_map(static fn (int $caller): string => sprintf('f("a%d ");', $caller), range(0, 6))),
        );
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame(
            array_map(static fn (int $caller): array => ['a' . $caller . ' ASC', 'a' . $caller . ' DESC'], range(0, 6)),
            array_map(static fn (Solution $solution): array => array_map(static fn (TextPattern $pattern): string => $pattern->display(), $solution->values[0]->patterns()), $solutions),
        );
        self::assertSame(array_fill(0, 7, false), array_map(static fn (Solution $solution): bool => $solution->combined, $solutions));
    }

    public function testSolveReadsWhatTheGoalsNeedWithTheNamesItWasGiven(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($x) { $d = new PDO(""); q($d->query($x)); } f("SELECT 1");');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $budget = new EvaluationBudget();
        $tree = new SourceTree([$file]);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $binder = new EntryBinder(new Callers(new CallerIndex([$file]), $index), $expressions, $budget);
        $plain = new Deriver($tree, new BackwardSlicer($tree, $budget), new SliceExecutor(), $expressions, $binder, $budget);
        $selective = new Deriver($tree, new BackwardSlicer($tree, $budget), new SliceExecutor(), $expressions, $binder, $budget, new FreeNames((new PdoExtension())->sinks()));

        $everything = $plain->solve($call, [$call->getArgs()[0]->value]);
        $onlyTheReceiver = $selective->solve($call, [$call->getArgs()[0]->value]);

        self::assertSame([['{main}', 'f']], array_map(static fn (Solution $solution): array => $solution->through, $everything));
        self::assertSame([['f']], array_map(static fn (Solution $solution): array => $solution->through, $onlyTheReceiver));
    }

    public function testSolveAtExitsReadsGoalsAtEveryReturnAndAtTheEnd(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class R { private $t; function set($a) { $f = function () { return 1; }; if ($a) { $this->t = "x"; return; } $this->t = "y"; } }',
        );
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solveAtExits($method, [new Expr\PropertyFetch(new Expr\Variable('this'), 't')], 0);

        self::assertSame(['y', 'x'], array_map(static fn (Solution $solution): string|int|float|bool|null => $solution->values[0]->soleLiteral()?->value, $solutions));
        self::assertSame([['R::set'], ['R::set']], array_map(static fn (Solution $solution): array => $solution->through, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
    }

    public function testSolveAtExitsEvaluatesTheGoalsInTheScopeOfTheBody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace N; class R { const T = "users"; private $t; function set() { $this->t = self::T; } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solveAtExits($method, [new Expr\PropertyFetch(new Expr\Variable('this'), 't'), new Expr\Variable('this')], 0);

        self::assertCount(1, $solutions);
        self::assertSame('users', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertSame('N\R', $solutions[0]->values[1]->soleObject()?->className);
        self::assertSame(['N\R::set'], $solutions[0]->through);
    }

    public function testSolveAtExitsTakesACallOfTheBodyItselfAsRecursion(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { private $t; function t() { $this->t = "x" . $this->t(); } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->solveAtExits($method, [new Expr\PropertyFetch(new Expr\Variable('this'), 't')], 0);

        self::assertCount(1, $solutions);
        self::assertSame('x{$}', $solutions[0]->values[0]->patterns()[0]->display());
        self::assertSame(Origin::Budget, $solutions[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('R::t()', $solutions[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testSolveAtExitsRemembersWhatItFoundForTheSameBodyGoalsAndDepth(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { private $t; function set() { $this->t = "x"; } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);
        $goal = new Expr\PropertyFetch(new Expr\Variable('this'), 't');

        $first = $deriver->solveAtExits($method, [$goal], 1);
        $again = $deriver->solveAtExits($method, [$goal], 1);

        self::assertCount(1, $first);
        self::assertSame('x', $first[0]->values[0]->soleLiteral()?->value);
        self::assertSame($first, $again);
    }

    public function testSolveAtExitsTellsBodiesGoalsAndDepthsApart(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class R { private $t; private $u; function __construct($t) { $this->t = $t; } function set() { $this->t = "x"; $this->u = "z"; } function reset() { $this->t = "y"; } } new R("users");',
        );
        $methods = (new NodeFinder())->findInstanceOf($file->statements, Stmt\ClassMethod::class);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);
        $t = new Expr\PropertyFetch(new Expr\Variable('this'), 't');
        $u = new Expr\PropertyFetch(new Expr\Variable('this'), 'u');

        $shallow = $deriver->solveAtExits($methods[0], [$t], 1);
        $deep = $deriver->solveAtExits($methods[0], [$t], 4);
        $set = $deriver->solveAtExits($methods[1], [$t], 0);
        $otherGoal = $deriver->solveAtExits($methods[1], [$u], 0);
        $reset = $deriver->solveAtExits($methods[2], [$t], 0);

        self::assertSame('users', $shallow[0]->values[0]->soleLiteral()?->value);
        self::assertSame(['{main}', 'R::__construct'], $shallow[0]->through);
        self::assertSame(Origin::Budget, $deep[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('x', $set[0]->values[0]->soleLiteral()?->value);
        self::assertSame('z', $otherGoal[0]->values[0]->soleLiteral()?->value);
        self::assertSame('y', $reset[0]->values[0]->soleLiteral()?->value);
    }

    public function testSolveAtExitsIsRememberedApartFromSolvingAtTheBody(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { private $t; function set() { $this->t = "x"; } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);
        $goal = new Expr\PropertyFetch(new Expr\Variable('this'), 't');

        $atTheBody = $deriver->solve($method, [$goal]);
        $atTheExits = $deriver->solveAtExits($method, [$goal], 0);

        self::assertSame(Origin::Unresolved, $atTheBody[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('x', $atTheExits[0]->values[0]->soleLiteral()?->value);
    }

    public function testSolveAtExitsDoesNotHandBackWhatItFoundOnceTheBudgetRanOut(): void
    {
        $budget = new EvaluationBudget(10);
        $file = (new SourceParser())->parse('t.php', '<?php class R { private $t; function set() { $this->t = "x"; } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), $budget))->deriverFor([$file]);
        $goal = new Expr\PropertyFetch(new Expr\Variable('this'), 't');
        array_map(static fn (int $step): bool => $budget->spend(), range(0, 10));

        $starved = $deriver->solveAtExits($method, [$goal], 1);
        $budget->reset();
        $again = $deriver->solveAtExits($method, [$goal], 1);

        self::assertCount(1, $starved);
        self::assertSame(Origin::Budget, $starved[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('$this->t', $starved[0]->values[0]->patterns()[0]->holes()[0]->expression);
        self::assertNotSame($starved, $again);
    }

    public function testRunReadsTheGoalsAfterRunningEachArrivalForward(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $t = "users"; $t .= " u"; q($t);');
        $assign = $file->statements[0];
        $append = $file->statements[1];
        self::assertInstanceOf(Stmt\Expression::class, $assign);
        self::assertInstanceOf(Stmt\Expression::class, $append);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solutions = $deriver->run(
            [new Arrival(null, new Pending([new SliceStep($append->expr), new SliceStep($assign->expr)], []))],
            [$call->getArgs()[0]->value],
            new FunctionScope('t.php'),
            0,
        );

        self::assertCount(1, $solutions);
        self::assertSame('users u', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertSame([FunctionScope::MAIN], $solutions[0]->through);
        self::assertFalse($solutions[0]->truncated);
        self::assertFalse($solutions[0]->combined);
    }

    public function testRunGivesOneSolutionPerArrivalWithOneValuePerGoal(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        $solutions = $deriver->run(
            [new Arrival(null, Pending::needing([])), new Arrival(null, Pending::needing([]))],
            [new String_('x'), new String_('y')],
            new FunctionScope('t.php'),
            0,
        );

        self::assertSame(
            [['x', 'y'], ['x', 'y']],
            array_map(static fn (Solution $solution): array => array_map(static fn (Domain $value): string|int|float|bool|null => $value->soleLiteral()?->value, $solution->values), $solutions),
        );
    }

    public function testRunMarksSolutionsTruncatedWhenTheirPathWasCutShort(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        $solutions = $deriver->run(
            [new Arrival(null, new Pending([], [], true)), new Arrival(null, Pending::needing([]))],
            [new String_('x')],
            new FunctionScope('t.php'),
            0,
        );

        self::assertSame([true, false], array_map(static fn (Solution $solution): bool => $solution->truncated, $solutions));
        self::assertSame([false, false], array_map(static fn (Solution $solution): bool => $solution->combined, $solutions));
    }

    public function testRunKeepsReadingPastTheSearchBudgetOnlyAtDepthZero(): void
    {
        $budget = new EvaluationBudget(2);
        $file = (new SourceParser())->parse('t.php', '<?php $t = "users"; q($t);');
        $assign = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $assign);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), $budget))->deriverFor([$file]);
        $arrival = new Arrival(null, new Pending([new SliceStep($assign->expr)], []));
        array_map(static fn (int $step): bool => $budget->spend(), range(0, 2));

        $closing = $deriver->run([$arrival], [$call->getArgs()[0]->value], new FunctionScope('t.php'), 0);
        $onTheWay = $deriver->run([$arrival], [$call->getArgs()[0]->value], new FunctionScope('t.php'), 1);

        self::assertTrue($deriver->spent());
        self::assertSame('users', $closing[0]->values[0]->soleLiteral()?->value);
        self::assertSame(Origin::Budget, $onTheWay[0]->values[0]->patterns()[0]->holes()[0]->origin);
        self::assertSame('$t', $onTheWay[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testRunKeepsNoMoreSolutionsThanTheLimit(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        $within = $deriver->run(array_fill(0, Deriver::MAX_SOLUTIONS, new Arrival(null, Pending::needing([]))), [new String_('x')], new FunctionScope('t.php'), 0);
        $beyond = $deriver->run(array_fill(0, Deriver::MAX_SOLUTIONS + 1, new Arrival(null, Pending::needing([]))), [new String_('x')], new FunctionScope('t.php'), 0);

        self::assertSame(array_fill(0, Deriver::MAX_SOLUTIONS, false), array_map(static fn (Solution $solution): bool => $solution->truncated, $within));
        self::assertSame(array_fill(0, Deriver::MAX_SOLUTIONS, true), array_map(static fn (Solution $solution): bool => $solution->truncated, $beyond));
    }

    public function testSpentTellsWhetherTheBudgetIsExhausted(): void
    {
        $budget = new EvaluationBudget(1);
        $deriver = (new Interpreter(new ProgramIndex(), [], $budget))->deriverFor([]);

        $fresh = $deriver->spent();
        $budget->spend();
        $atTheLimit = $deriver->spent();
        $budget->spend();
        $beyond = $deriver->spent();

        self::assertFalse($fresh);
        self::assertFalse($atTheLimit);
        self::assertTrue($beyond);
    }

    public function testSolveUnlessSpentSolvesWhileTheBudgetLasts(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($t) { q($t); } f("a");');
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $solved = $deriver->solveUnlessSpent($call, [$call->getArgs()[0]->value], 1);
        $deep = $deriver->solveUnlessSpent($call, [$call->getArgs()[0]->value], 4);

        self::assertSame('a', $solved[0]->values[0]->soleLiteral()?->value);
        self::assertSame($deriver->solve($call, [$call->getArgs()[0]->value], 1), $solved);
        self::assertSame(Origin::Budget, $deep[0]->values[0]->patterns()[0]->holes()[0]->origin);
    }

    public function testSolveUnlessSpentGivesNothingOnceTheBudgetIsSpent(): void
    {
        $budget = new EvaluationBudget(10);
        $file = (new SourceParser())->parse('t.php', '<?php $t = "a"; q($t);');
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks(), $budget))->deriverFor([$file]);
        array_map(static fn (int $step): bool => $budget->spend(), range(0, 10));

        $solutions = $deriver->solveUnlessSpent($call, [$call->getArgs()[0]->value], 0);

        self::assertSame([], $solutions);
        self::assertCount(1, $deriver->solve($call, [$call->getArgs()[0]->value]));
    }

    public function testBoundedKeepsSolutionsWithinTheLimitAsTheyAre(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);
        $solutions = array_map(static fn (int $way): Solution => new Solution([Domain::literal($way)], ['f']), range(1, Deriver::MAX_SOLUTIONS));

        $bounded = $deriver->bounded($solutions);

        self::assertSame($solutions, $bounded);
    }

    public function testBoundedMarksSolutionsBeyondTheLimitTruncated(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);
        $solutions = array_map(
            static fn (int $way): Solution => new Solution([Domain::literal($way)], ['{main}', 'f' . $way], false, $way % 2 === 0),
            range(0, Deriver::MAX_SOLUTIONS),
        );

        $bounded = $deriver->bounded($solutions);

        self::assertSame(range(0, Deriver::MAX_SOLUTIONS - 1), array_map(static fn (Solution $solution): string|int|float|bool|null => $solution->values[0]->soleLiteral()?->value, $bounded));
        self::assertSame(
            array_map(static fn (int $way): array => ['{main}', 'f' . $way], range(0, Deriver::MAX_SOLUTIONS - 1)),
            array_map(static fn (Solution $solution): array => $solution->through, $bounded),
        );
        self::assertSame(array_fill(0, Deriver::MAX_SOLUTIONS, true), array_map(static fn (Solution $solution): bool => $solution->truncated, $bounded));
        self::assertSame(
            array_map(static fn (int $way): bool => $way % 2 === 0, range(0, Deriver::MAX_SOLUTIONS - 1)),
            array_map(static fn (Solution $solution): bool => $solution->combined, $bounded),
        );
    }

    #[DataProvider('providerScopeOf')]
    public function testScopeOfNamesTheFileTheBodyAndTheClassOfAPoint(string $code, string $function, ?string $className): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $scope = $deriver->scopeOf($call);

        self::assertSame('t.php', $scope->file);
        self::assertSame($function, $scope->function);
        self::assertSame($className, $scope->className);
        self::assertSame([$function], $scope->stack);
    }

    #[DataProvider('providerScopeOfAClosure')]
    public function testScopeOfAClosureNamesTheEnclosingBody(string $code, string $function, ?string $className): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        $scope = $deriver->scopeOf($call);

        self::assertSame('t.php', $scope->file);
        self::assertSame($function, $scope->function);
        self::assertSame($className, $scope->className);
        self::assertSame([$function], $scope->stack);
    }

    public function testScopeOfANodeInNoRecordedFileHasNoFile(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        $scope = $deriver->scopeOf(new Expr\Closure());

        self::assertSame('', $scope->file);
        self::assertSame(FunctionScope::MAIN, $scope->function);
        self::assertNull($scope->className);
        self::assertSame([FunctionScope::MAIN], $scope->stack);
    }

    #[DataProvider('providerClassOf')]
    public function testClassOfNamesTheNearestClassANodeIsWrittenIn(string $code, ?string $className): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $call = (new NodeFinder())->findFirst($file->statements, static fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'q');
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        self::assertSame($className, $deriver->classOf($call));
    }

    public function testClassOfFallsBackToTheWrittenNameWhenNamesWereNotResolved(): void
    {
        $method = new Stmt\ClassMethod('m');
        $class = new Stmt\Class_('Plain', ['stmts' => [$method]]);
        $class->namespacedName = null;
        $method->setAttribute('parent', $class);
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        self::assertSame('Plain', $deriver->classOf($method));
    }

    public function testClassOfANodeWithoutParentsIsNull(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        self::assertNull($deriver->classOf(new Stmt\ClassMethod('m')));
    }

    /**
     * @param class-string<Node\FunctionLike> $type
     */
    #[DataProvider('providerNameOf')]
    public function testNameOfNamesABodyTheWayItIsReported(string $code, string $type, string $name): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $body = (new NodeFinder())->findFirstInstanceOf($file->statements, $type);
        self::assertInstanceOf(Node\FunctionLike::class, $body);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), (new PdoExtension())->sinks()))->deriverFor([$file]);

        self::assertSame($name, $deriver->nameOf($body));
    }

    public function testNameOfAMethodOutsideEveryClassHasNoClassBeforeIt(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        self::assertSame('::lone', $deriver->nameOf(new Stmt\ClassMethod('lone')));
    }

    public function testNameOfAFunctionWhoseNameWasNotResolvedIsItsWrittenName(): void
    {
        $function = new Stmt\Function_('plain');
        $function->namespacedName = null;
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        self::assertSame('plain', $deriver->nameOf($function));
    }

    public function testNameOfAClosureWithoutParentsIsNamedAfterTheFile(): void
    {
        $deriver = (new Interpreter(new ProgramIndex(), []))->deriverFor([]);

        self::assertSame('{main}::{closure}', $deriver->nameOf(new Expr\Closure()));
    }
}
