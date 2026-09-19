<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\Origin;

#[CoversClass(ExpressionEvaluator::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(Environment::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
final class ExpressionEvaluatorTest extends TestCase
{
    #[DataProvider('providerEvaluate')]
    public function testEvaluate(string $expression, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = ' . $expression . ';');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame($expected, $environment->read('result')->patterns()[0]->display());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerEvaluate(): array
    {
        return [
            ['"SELECT 1"', 'SELECT 1'],
            ['7', '7'],
            ['1.5', '1.5'],
            ['true', '1'],
            ['null', ''],
            ['"a" . "b"', 'ab'],
            ['"SELECT " . 1', 'SELECT 1'],
            ['PHP_EOL', "\n"],
            ['["a"][0]', 'a'],
            ['(string) 12', '12'],
            ['(int) "12"', '12'],
            ['(bool) 1', '1'],
            ['(float) "1.5"', '1.5'],
            ['strtoupper("a")', 'A'],
            ['2 <=> 1', '{$}'],
        ];
    }

    public function testEvaluateInterpolationIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php "a{$b}c";');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Scalar\InterpolatedString::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $result = $expressions->evaluateInterpolation($node, new Environment(), new FunctionScope('t.php'));

        self::assertSame('a{$}c', $result->patterns()[0]->display());
    }

    public function testEvaluateTernaryIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $c ? "a" : "b";');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());

        self::assertCount(2, $expressions->evaluateTernary($node, new Environment(), new FunctionScope('t.php'))->terms);
    }

    public function testEvaluateAssignIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x";');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $result = $expressions->evaluateAssign($node, $environment, new FunctionScope('t.php'));

        self::assertSame('x', $result->soleLiteral()?->value);
        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateAppendIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a .= "y";');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\AssignOp\Concat::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment(['a' => \SqlCatalog\Evaluation\Domain::literal('x')]);
        $result = $expressions->evaluateAppend($node, $environment, new FunctionScope('t.php'));

        self::assertSame('xy', $result->soleLiteral()?->value);
    }

    public function testEvaluateOfAnInterpolatedStringJoinsItsParts(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $t = "users"; $result = "SELECT * FROM {$t}";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('SELECT * FROM users', $environment->read('result')->soleLiteral()?->value);
    }

    public function testEvaluateOfATernaryKeepsBothBranches(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = $c ? "a" : "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateOfAMatchKeepsEveryArm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = match ($c) { 1 => "a", default => "b" };');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateOfAnAppendingAssignmentExtendsTheVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "SELECT 1"; $sql .= " FROM t";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('SELECT 1 FROM t', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateGivesUpOnceTheBudgetIsSpent(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), [], new \SqlCatalog\Analysis\EvaluationBudget(0)))
            ->evaluatorFor(new StatementRecorder());
        $result = $expressions->evaluate(new String_('SELECT 1'), new Environment(), new FunctionScope('t.php'));
        self::assertFalse($result->isExact());
    }

    public function testBodiesWalksWithThisEvaluator(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        self::assertSame($expressions->bodies(), $expressions->bodies());
    }

    public function testEvaluateScalarOnlyAnswersForLiterals(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        self::assertSame('a', $expressions->evaluateScalar(new String_('a'))?->soleLiteral()?->value);
        self::assertSame(1, $expressions->evaluateScalar(new Int_(1))?->soleLiteral()?->value);
        self::assertSame(1.5, $expressions->evaluateScalar(new Float_(1.5))?->soleLiteral()?->value);
        self::assertNull($expressions->evaluateScalar(new Variable('a')));
    }

    public function testEvaluateOperatorOnlyAnswersForOperators(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $scope = new FunctionScope('t.php');
        self::assertNull($expressions->evaluateOperator(new Variable('a'), $environment, $scope));
        self::assertNotNull($expressions->evaluateOperator(new BooleanNot(new Variable('a')), $environment, $scope));
    }

    public function testEvaluatePredicateReportsTheResultingType(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        self::assertSame('bool', $expressions->evaluatePredicate(new BooleanNot(new Variable('a')))?->type()->display());
        self::assertSame('int', $expressions->evaluatePredicate(new \PhpParser\Node\Expr\PostInc(new Variable('a')))?->type()->display());
        self::assertSame('int', $expressions->evaluatePredicate(new \PhpParser\Node\Expr\PreDec(new Variable('a')))?->type()->display());
        self::assertNull($expressions->evaluatePredicate(new Variable('a')));
    }

    public function testEvaluateCastOfAnObjectKnowsOnlyThatItIsOne(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $cast = new Cast\Object_(new Variable('a'));
        $result = $expressions->evaluateCast($cast, new Environment(), new FunctionScope('t.php'));
        self::assertSame('object', $result->type()->display());
    }

    public function testEvaluateCastEndsTheTrailBackToExternalInput(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = (int) $_GET["id"];');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        $holes = $environment->read('result')->patterns()[0]->holes();
        self::assertSame(Origin::Call, $holes[0]->origin);
    }

    public function testCastLiteralWritesTheValueAsTheTypeItIsCastTo(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        self::assertSame(12, $expressions->castLiteral('12', 'int')->soleLiteral()?->value);
        self::assertSame(1.5, $expressions->castLiteral('1.5', 'float')->soleLiteral()?->value);
        self::assertTrue($expressions->castLiteral(1, 'bool')->soleLiteral()?->value);
    }

    public function testEvaluateInterpolationJoinsLiteralPartsAndExpressions(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = "a{$b}c";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('a{$}c', $environment->read('result')->patterns()[0]->display());
    }

    public function testEvaluateTernaryHandlesTheShortForm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = "a" ?: "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateMatchOfNoArmsKnowsNothing(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $match = new \PhpParser\Node\Expr\Match_(new Variable('a'), []);
        $result = $expressions->evaluateMatch($match, new Environment(), new FunctionScope('t.php'));
        self::assertSame('mixed', $result->type()->display());
    }

    public function testEvaluateAssignWritesIntoTheEnvironment(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateAppendStartsFromWhatTheVariableAlreadyHolds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $a .= "y"; $a .= "z";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('xyz', $environment->read('a')->soleLiteral()?->value);
    }
}
