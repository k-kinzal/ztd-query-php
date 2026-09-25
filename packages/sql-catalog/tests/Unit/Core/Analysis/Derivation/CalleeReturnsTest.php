<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\CalleeReturns;
use SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Evaluation\CallResults;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Php\FunctionShape;
use SqlCatalog\Core\Php\ParameterShape;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BranchArms::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(CallResults::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
final class CalleeReturnsTest extends TestCase
{
    public function testValueOfReadsWhatTheCalleeReturns(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(string $t): string { $s = "SELECT * FROM " . $t; return $s; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $value = $returns->valueOf($callee, [Domain::literal('users')], new FunctionScope('t.php', FunctionScope::MAIN, null, [FunctionScope::MAIN]), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        self::assertSame('SELECT * FROM users', $value->soleLiteral()?->value);
        self::assertSame(1, $remembered->count());
        self::assertSame($value, $remembered->recall($remembered->keyFor('q', [Domain::literal('users')])));
    }

    public function testValueOfLeavesAFunctionWithoutABodyAsACallItDidNotFollow(): void
    {
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree(), $budget), new SliceExecutor(budget: $budget), $budget);

        $value = $returns->valueOf(
            new FunctionShape('App\\q', [], TypeShape::of(['string'])),
            [],
            new FunctionScope('t.php'),
            (new Interpreter(new ProgramIndex(), []))->evaluatorFor(),
        );

        $term = $value->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertCount(1, $value->terms);
        self::assertSame(Origin::Call, $term->origin);
        self::assertSame('App\\q()', $term->expression);
        self::assertSame('string', $term->type->display());
    }

    public function testValueOfLeavesAnAbstractMethodAsACallItDidNotFollow(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; interface Finder { public function find(): int; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findMethod('App\\Finder', 'find');
        self::assertInstanceOf(\SqlCatalog\Core\Php\MethodShape::class, $callee);

        $value = $returns->valueOf($callee, [], new FunctionScope('t.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        $term = $value->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(Origin::Call, $term->origin);
        self::assertSame('App\\Finder::find()', $term->expression);
        self::assertSame('int', $term->type->display());
        self::assertSame(0, $remembered->count());
    }

    public function testValueOfStopsAtTheDepthLimitAndFollowsJustBelowIt(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(): string { return "SELECT 1"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(maxDepth: 2);
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $atLimit = $returns->valueOf($callee, [], new FunctionScope('t.php', 'b', null, [FunctionScope::MAIN, 'a', 'b']), $expressions);
        $belowLimit = $returns->valueOf($callee, [], new FunctionScope('t.php', 'a', null, [FunctionScope::MAIN, 'a']), $expressions);

        $term = $atLimit->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(Origin::Budget, $term->origin);
        self::assertSame('q()', $term->expression);
        self::assertSame('string', $term->type->display());
        self::assertSame('SELECT 1', $belowLimit->soleLiteral()?->value);
    }

    public function testValueOfStopsAtACallAlreadyBeingFollowed(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(): string { return "SELECT 1"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $value = $returns->valueOf($callee, [], new FunctionScope('t.php', 'q', null, ['q']), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        $term = $value->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(Origin::Budget, $term->origin);
        self::assertSame('q()', $term->expression);
        self::assertSame(0, $remembered->count());
    }

    public function testValueOfStopsOnceTheBudgetIsExhausted(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(): string { return "SELECT 1"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(maxSteps: 0);
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);
        self::assertFalse($budget->spend());

        $value = $returns->valueOf($callee, [], new FunctionScope('t.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        $term = $value->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(Origin::Budget, $term->origin);
        self::assertSame('q()', $term->expression);
        self::assertSame(0, $remembered->count());
    }

    public function testValueOfAnswersACallWithTheSameArgumentsFromMemory(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(string $t): string { return "SELECT * FROM " . $t; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $remembered = new CallResults();
        $remembered->remember($remembered->keyFor('q', [Domain::literal('users')]), Domain::literal('remembered'));
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);
        $scope = new FunctionScope('t.php');

        $same = $returns->valueOf($callee, [Domain::literal('users')], $scope, $expressions);
        $other = $returns->valueOf($callee, [Domain::literal('orders')], $scope, $expressions);
        $again = $returns->valueOf($callee, [Domain::literal('orders')], $scope, $expressions);

        self::assertSame('remembered', $same->soleLiteral()?->value);
        self::assertSame('SELECT * FROM orders', $other->soleLiteral()?->value);
        self::assertSame($other, $again);
        self::assertSame(2, $remembered->count());
    }

    public function testReadUnitesWhatEveryReturnCanReturn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q($c) { if ($c) { return "a"; } return "b"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $value = $returns->read($callee, 'q', 'the key', [Domain::unknown()], new FunctionScope('t.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        self::assertSame(['a', 'b'], array_map(static fn (TextPattern $pattern): string => $pattern->display(), $value->patterns()));
        self::assertSame($value, $remembered->recall('the key'));
        self::assertSame(1, $remembered->count());
    }

    public function testReadReturnsNullFromABodyWithoutAReturn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q() { $x = 1; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $value = $returns->read($callee, 'q', 'q|', [], new FunctionScope('t.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        self::assertNotNull($value->soleLiteral());
        self::assertNull($value->soleLiteral()->value);
    }

    public function testReadDoesNotRememberWhatAnExhaustedBudgetCutShort(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q(string $t): string { $s = "SELECT * FROM " . $t; return $s; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(maxSteps: 0);
        $remembered = new CallResults();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget, null, $remembered);
        $callee = $index->findFunction('q');
        self::assertInstanceOf(FunctionShape::class, $callee);
        self::assertFalse($budget->spend());

        $value = $returns->read($callee, 'q', 'the key', [Domain::literal('users')], new FunctionScope('t.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        $term = $value->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(Origin::Budget, $term->origin);
        self::assertSame(0, $remembered->count());
        self::assertNull($remembered->recall('the key'));
    }

    public function testReadRunsAMethodInItsOwnClassAndAFunctionInTheCallersClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Repo { const T = "repo"; public function table() { return self::T; } }'
            . ' class Caller { const T = "caller"; } function table() { return self::T; }',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $scope = new FunctionScope('t.php', 'Caller::run', 'Caller', ['Caller::run']);
        $method = $index->findMethod('Repo', 'table');
        self::assertInstanceOf(\SqlCatalog\Core\Php\MethodShape::class, $method);
        $function = $index->findFunction('table');
        self::assertInstanceOf(FunctionShape::class, $function);

        $fromMethod = $returns->read($method, 'Repo::table', 'method', [], $scope, $expressions);
        $fromFunction = $returns->read($function, 'table', 'function', [], $scope, $expressions);

        self::assertSame('repo', $fromMethod->soleLiteral()?->value);
        self::assertSame('caller', $fromFunction->soleLiteral()?->value);
    }

    public function testReadEntersTheCalleeSoACallBackIntoItIsNotFollowedAgain(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function r($n) { return r($n) . "x"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $callee = $index->findFunction('r');
        self::assertInstanceOf(FunctionShape::class, $callee);

        $value = $returns->read($callee, 'r', 'r|', [Domain::literal(1)], new FunctionScope('t.php', FunctionScope::MAIN, null, [FunctionScope::MAIN]), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));

        self::assertCount(1, $value->patterns());
        self::assertSame('{$}x', $value->patterns()[0]->display());
        self::assertCount(1, $value->patterns()[0]->holes());
        self::assertSame(Origin::Budget, $value->patterns()[0]->holes()[0]->origin);
        self::assertSame('r()', $value->patterns()[0]->holes()[0]->expression);
    }

    public function testReturnedReadsWhatTheReturnDependsOnFromTheStart(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q($t) { $unused = 1; $s = "SELECT * FROM " . $t; return $s; }');
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $return = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Return_::class);
        self::assertInstanceOf(Stmt\Return_::class, $return);
        $start = new Environment(['t' => Domain::literal('users')]);

        $value = $returns->returned($return, $start, new FunctionScope('t.php', 'q', null, ['q']), (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor([$file]));

        self::assertSame('SELECT * FROM users', $value->soleLiteral()?->value);
        self::assertSame(['t'], $start->names());
    }

    public function testReturnedUnitesEveryRunOfTheSlice(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q($t) { if ($c) { $t = "x"; } $s = $c ? "a" : "b"; return $s . $t; }');
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $return = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Return_::class);
        self::assertInstanceOf(Stmt\Return_::class, $return);

        $value = $returns->returned($return, new Environment(['t' => Domain::literal('p')]), new FunctionScope('t.php', 'q', null, ['q']), (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor([$file]));

        self::assertSame(['ax', 'bx', 'ap', 'bp'], array_map(static fn (TextPattern $pattern): string => $pattern->display(), $value->patterns()));
    }

    public function testReturnedReadsNullFromABareReturn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function q() { return; }');
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $return = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Return_::class);
        self::assertInstanceOf(Stmt\Return_::class, $return);

        $value = $returns->returned($return, new Environment(), new FunctionScope('t.php', 'q', null, ['q']), (new Interpreter(new ProgramIndex(), [], $budget))->evaluatorFor([$file]));

        self::assertNotNull($value->soleLiteral());
        self::assertNull($value->soleLiteral()->value);
    }

    public function testParametersBindsEachArgumentToThePositionItIsPassedIn(): void
    {
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree(), $budget), new SliceExecutor(budget: $budget), $budget);

        $environment = $returns->parameters(
            [
                new ParameterShape('a', TypeShape::of(['string']), new String_('unused')),
                new ParameterShape('b', TypeShape::of(['int'])),
            ],
            [Domain::literal('x'), Domain::literal(2)],
            new FunctionScope('t.php'),
            (new Interpreter(new ProgramIndex(), []))->evaluatorFor(),
        );

        self::assertSame(['a', 'b'], $environment->names());
        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
        self::assertSame(2, $environment->read('b')->soleLiteral()?->value);
    }

    public function testParametersFallsBackToTheDefaultOfAParameterNotPassed(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class Repo { const T = "repo"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);

        $environment = $returns->parameters(
            [
                new ParameterShape('a', TypeShape::of(['string'])),
                new ParameterShape('b', TypeShape::of(['string']), new String_('d')),
                new ParameterShape('c', TypeShape::of(['string']), new ClassConstFetch(new Name('self'), 'T')),
            ],
            [Domain::literal('x')],
            new FunctionScope('t.php', 'Repo::find', 'Repo', ['Repo::find']),
            (new Interpreter($index, [], $budget))->evaluatorFor([$file]),
        );

        self::assertSame(['a', 'b', 'c'], $environment->names());
        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
        self::assertSame('d', $environment->read('b')->soleLiteral()?->value);
        self::assertSame('repo', $environment->read('c')->soleLiteral()?->value);
    }

    public function testParametersLeavesAParameterNeitherPassedNorDefaultedOpen(): void
    {
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree(), $budget), new SliceExecutor(budget: $budget), $budget);

        $environment = $returns->parameters(
            [new ParameterShape('a', TypeShape::of(['string'])), new ParameterShape('b', TypeShape::of(['int']))],
            [Domain::literal('x')],
            new FunctionScope('t.php'),
            (new Interpreter(new ProgramIndex(), []))->evaluatorFor(),
        );

        $term = $environment->read('b')->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertCount(1, $environment->read('b')->terms);
        self::assertSame(Origin::Parameter, $term->origin);
        self::assertSame('$b', $term->expression);
        self::assertSame('int', $term->type->display());
    }

    public function testParametersLeavesAVariadicParameterOpenWhateverIsPassed(): void
    {
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree(), $budget), new SliceExecutor(budget: $budget), $budget);

        $environment = $returns->parameters(
            [new ParameterShape('a', TypeShape::of(['string'])), new ParameterShape('rest', TypeShape::of(['string']), null, true)],
            [Domain::literal('x'), Domain::literal('y'), Domain::literal('z')],
            new FunctionScope('t.php'),
            (new Interpreter(new ProgramIndex(), []))->evaluatorFor(),
        );

        $term = $environment->read('rest')->terms[0];
        self::assertInstanceOf(OpaqueTerm::class, $term);
        self::assertSame(['a', 'rest'], $environment->names());
        self::assertSame(Origin::Parameter, $term->origin);
        self::assertSame('...$rest', $term->expression);
        self::assertSame('string', $term->type->display());
    }

    public function testReturnsInFindsEveryReturnOfTheBodyInTheOrderWritten(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php return 0; if ($a) { return 1; } elseif ($b) { return 2; } else { return 3; }'
            . ' try { return 4; } catch (E $e) { return 5; } finally { return 6; }'
            . ' switch ($x) { case 1: return 7; } foreach ($xs as $x) { return 8; } $y = 9;',
        );
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);

        $found = $returns->returnsIn($file->statements);

        self::assertSame(
            ['return 0;', 'return 1;', 'return 2;', 'return 3;', 'return 4;', 'return 5;', 'return 6;', 'return 7;', 'return 8;'],
            array_map(static fn (Stmt\Return_ $return): string => (new Standard())->prettyPrint([$return]), $found),
        );
    }

    public function testReturnsInLeavesOutTheReturnsOfFunctionsAndClassesDeclaredInTheBody(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function inner() { return 1; } class Nested { public function m() { return 2; } }'
            . ' $f = function () { return 3; }; return 4;',
        );
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);

        $found = $returns->returnsIn($file->statements);

        self::assertSame(['return 4;'], array_map(static fn (Stmt\Return_ $return): string => (new Standard())->prettyPrint([$return]), $found));
        self::assertSame([], $returns->returnsIn([]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLocalReturns')]
    public function testValueOfKeepsAbsenceDistinctFromUnresolvedReturns(string $body, string $expected, bool $combined): void
    {
        $file = (new SourceParser())->parse('a.php', '<?php function fragment($input) { ' . $body . ' }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(maxLoopPasses: 1);
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([$file]), $budget), new SliceExecutor(budget: $budget), $budget);
        $callee = $index->findFunction('fragment');
        self::assertInstanceOf(FunctionShape::class, $callee);
        $value = $returns->valueOf($callee, [Domain::literal('known')], new FunctionScope('a.php'), (new Interpreter($index, [], $budget))->evaluatorFor([$file]));
        self::assertSame($expected, $value->signature());
        self::assertSame($combined, $value->combined);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function providerLocalReturns(): array
    {
        return [
            'absent local' => ['return $tail;', 'literal:null:', false],
            'bound parameter' => ['return $input;', 'literal:string:known', false],
            'unresolved receiver' => ['return $this;', 'opaque:mixed:unresolved', false],
            'separate arm writes' => ['true ? $tail = "a" : $tail = "b"; return $tail;', 'literal:string:a|literal:string:b', true],
            'truncated loop' => ['$tail = ""; while ($input) { $tail .= "x"; } return $tail;', 'literal:string:|literal:string:x|opaque:string:budget', false],
        ];
    }


    public function testCacheableRejectsAllocationsEvenInsideArrays(): void
    {
        $budget = new EvaluationBudget();
        $returns = new CalleeReturns(new BackwardSlicer(new SourceTree([]), $budget), new SliceExecutor(), $budget);
        $object = Domain::of(new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a'));
        self::assertFalse($returns->cacheable($object));
        self::assertFalse($returns->cacheable(Domain::of(new \SqlCatalog\Core\Evaluation\ArrayTerm([new \SqlCatalog\Core\Evaluation\ArrayEntry(null, $object)]))));
        self::assertTrue($returns->cacheable(Domain::literal('sql')));
    }
}
