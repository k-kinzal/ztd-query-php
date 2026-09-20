<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Stmt\Nop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BodyWalker;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\PathSet;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\SourceParser;

#[CoversClass(BodyWalker::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(Environment::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(PathSet::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
final class BodyWalkerTest extends TestCase
{
    #[DataProvider('providerWalk')]
    public function testWalk(string $code, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame($expected, $environment->read('sql')->patterns()[0]->display());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerWalk(): array
    {
        return [
            ['<?php $sql = "a";', 'a'],
            ['<?php $sql = "a"; { $sql = "b"; }', 'b'],
            ['<?php namespace N; $sql = "a";', 'a'],
            ['<?php $sql = "a"; unset($sql); $sql = "b";', 'b'],
            ['<?php $sql = "a"; try { $sql = "b"; } catch (\\Throwable $e) { $sql = "c"; } finally { }', 'b'],
        ];
    }

    public function testWalkOfNothingReturnsNull(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $returned = $expressions->bodies()->walk([], new PathSet(), new FunctionScope('t.php'));
        self::assertNull($returned->soleLiteral()?->value);
    }

    public function testWalkOneOfAStatementWithNoValueReturnsNothing(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        self::assertNull($expressions->bodies()->walkOne(new Nop(), new PathSet(), new FunctionScope('t.php')));
    }

    public function testWalkCollectsWhatABodyReturns(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php return "a";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $returned = $expressions->bodies()->walk($file->statements, new PathSet(), new FunctionScope('t.php'));
        self::assertSame('a', $returned->soleLiteral()?->value);
    }

    public function testWalkConditionalKeepsWhatEveryBranchMayLeaveBehind(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "base"; if ($c) { $sql = "a"; } elseif ($d) { $sql = "b"; }');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(3, $environment->read('sql')->terms);
    }

    public function testWalkConditionalWithAnElseDropsTheValueFromBefore(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "base"; if ($c) { $sql = "a"; } else { $sql = "b"; }');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('sql')->terms);
    }

    public function testWalkSwitchKeepsEveryCase(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php switch ($c) { case 1: $sql = "a"; break; default: $sql = "b"; }',
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('sql')->terms);
    }

    public function testWalkBranchesOfNothingLeavesTheEnvironmentAlone(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = PathSet::of(new Environment(['sql' => Domain::literal('a')]));
        $expressions->bodies()->walkBranches([], $paths, new FunctionScope('t.php'), false);
        self::assertSame('a', $paths->join()->read('sql')->soleLiteral()?->value);
    }

    public function testWalkLoopWidensWhatKeepsGrowing(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php $sql = "WHERE 1"; foreach ($filters as $f) { $sql .= " AND x"; }',
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        $displayed = array_map(
            static fn (\SqlCatalog\Text\TextPattern $pattern): string => $pattern->display(),
            $environment->read('sql')->patterns(),
        );
        self::assertContains('WHERE 1', $displayed);
        self::assertContains('WHERE 1 AND x{$}', $displayed);
    }

    public function testWalkLoopHandlesEveryLoopForm(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php $sql = "a"; while ($c) { $sql = "b"; } do { $sql = "c"; } while ($c); for ($i = 0; $i < 3; $i++) { $sql = "d"; }',
        );
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertNotSame([], $environment->read('sql')->terms);
    }

    public function testWalkConditionalIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php if ($c) { return "a"; } else { return "b"; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\If_::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $returned = $walker->walkConditional($statement, new PathSet(), new FunctionScope('t.php'));

        self::assertNotNull($returned);
        self::assertCount(2, $returned->terms);
    }

    public function testWalkSwitchIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php switch ($c) { case 1: return "a"; default: return "b"; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Switch_::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $returned = $walker->walkSwitch($statement, new PathSet(), new FunctionScope('t.php'));

        self::assertNotNull($returned);
        self::assertCount(2, $returned->terms);
    }

    public function testWalkLoopIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while ($c) { $sql = "a"; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\While_::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $paths = new PathSet();
        $walker->walkLoop($statement, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('a', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testWalkOtherIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php { $sql = "a"; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Block::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $paths = new PathSet();
        $walker->walkOther($statement, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('a', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testWalkTryIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php try { $sql = "a"; } catch (\RuntimeException $e) { $sql = "b"; }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\TryCatch::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $paths = new PathSet();
        $walker->walkTry($statement, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('sql')->terms);
    }

    public function testBindIterationIsCalledWithTheStatementItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach (["a"] as $row) { }');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Foreach_::class, $statement);

        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $paths = new PathSet();
        $walker->bindIteration($statement, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('a', $environment->read('row')->soleLiteral()?->value);
    }

    public function testIsLoopRecognisesEveryLoopStatement(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php while (true) {} foreach ([] as $a) {} $x = 1;');
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        self::assertTrue($walker->isLoop($file->statements[0]));
        self::assertTrue($walker->isLoop($file->statements[1]));
        self::assertFalse($walker->isLoop($file->statements[2]));
    }

    public function testLoopBodyOfSomethingThatIsNotALoopIsEmpty(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        self::assertSame([], $walker->loopBody(new Nop(), new PathSet(), new FunctionScope('t.php')));
    }

    public function testBindIterationBindsTheValueVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php foreach (["a", "b"] as $k => $sql) { }');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('sql')->terms);
    }

    public function testElementsOfAnEmptyArrayKnowsNothing(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        self::assertSame('mixed', $walker->elementsOf(new ArrayTerm([]))->type()->display());
    }

    public function testWidenLeavesAStableVariableAlone(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $first = new Environment(['a' => Domain::literal('x')]);
        $second = new Environment(['a' => Domain::literal('x')]);
        self::assertSame('x', $walker->widen($first, $second)->read('a')->soleLiteral()?->value);
    }

    public function testWidenFoldsAVariableThatKeptChanging(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $first = new Environment(['a' => Domain::literal('x')]);
        $second = new Environment(['a' => Domain::literal('y')]);
        self::assertCount(1, $walker->widen($first, $second)->read('a')->terms);
    }

    public function testWidenPathsWidensEachPathAgainstItsSecondPass(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $first = PathSet::of(new Environment(['a' => Domain::literal('x')]));
        $second = PathSet::of(new Environment(['a' => Domain::literal('y')]));

        self::assertCount(1, $walker->widenPaths($first, $second)->join()->read('a')->terms);
    }

    public function testWidenPathsJoinsWhenThePathCountsDiffer(): void
    {
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $first = new PathSet([new Environment(['a' => Domain::literal('x')]), new Environment(['a' => Domain::literal('y')])]);
        $second = PathSet::of(new Environment(['a' => Domain::literal('z')]));

        self::assertCount(1, $walker->widenPaths($first, $second)->environments());
    }

    public function testEvaluateEverywhereCoversEveryPath(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql;');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $paths = new PathSet([
            new Environment(['sql' => Domain::literal('a')]),
            new Environment(['sql' => Domain::literal('b')]),
        ]);

        $read = $walker->evaluateEverywhere($statement->expr, $paths, new FunctionScope('t.php'));

        self::assertCount(2, $read->terms);
    }

    public function testWalkOtherEvaluatesTheExpressionsOfEchoAndThrow(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php echo "a"; global $g; static $s;');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = PathSet::of(new Environment(['g' => Domain::literal('x')]));
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertFalse($environment->has('g'));
    }

    public function testRebindDeclaredDropsWhatAStaticDeclarationRebinds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php static $s = 1;');
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $environment = new Environment(['s' => Domain::literal('x')]);
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Static_::class, $statement);
        $walker->rebindDeclared($statement, PathSet::of($environment));
        self::assertFalse($environment->has('s'));
    }

    public function testRebindDeclaredBindsAGlobalWhateverDeclaresItsClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php' . "\n" . '/** @global \\PDO $db */' . "\n" . 'function f() { global $db; }',
        );
        $walker = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder())->bodies();
        $function = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $function);
        $statement = $function->stmts[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Global_::class, $statement);
        $environment = new Environment();

        $walker->rebindDeclared($statement, PathSet::of($environment));

        self::assertSame(['PDO'], $environment->read('db')->type()->names);
    }

    public function testWalkTryKeepsWhatTheHandlersReturn(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php try { return "a"; } catch (\\RuntimeException $e) { return "b"; }');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $returned = $expressions->bodies()->walk($file->statements, new PathSet(), new FunctionScope('t.php'));
        self::assertCount(2, $returned->terms);
    }
}
