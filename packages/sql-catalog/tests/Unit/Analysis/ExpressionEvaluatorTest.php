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
use SqlCatalog\Evaluation\PathSet;
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
#[UsesClass(\SqlCatalog\AnalysisOptions::class)]
#[UsesClass(\SqlCatalog\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analyzer::class)]
#[UsesClass(\SqlCatalog\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Catalog\Catalog::class)]
#[UsesClass(\SqlCatalog\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Catalog\Resolution::class)]
#[UsesClass(PathSet::class)]
#[UsesClass(\SqlCatalog\Extension\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Extension\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Extension\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Sql\TableReader::class)]
final class ExpressionEvaluatorTest extends TestCase
{
    #[DataProvider('providerEvaluate')]
    public function testEvaluate(string $expression, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = ' . $expression . ';');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

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
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('SELECT * FROM users', $environment->read('result')->soleLiteral()?->value);
    }

    public function testEvaluateOfATernaryKeepsBothBranches(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = $c ? "a" : "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateOfAMatchKeepsEveryArm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = match ($c) { 1 => "a", default => "b" };');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateOfAnAppendingAssignmentExtendsTheVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "SELECT 1"; $sql .= " FROM t";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

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

    public function testEvaluateResultReportsTheTypeTheOperatorProduces(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $scope = new FunctionScope('t.php');

        self::assertSame('bool', $expressions->evaluateResult(new BooleanNot(new Variable('a')), $environment, $scope)?->type()->display());
        self::assertNull($expressions->evaluateResult(new Variable('a'), $environment, $scope));
    }

    public function testResultTypeNamesTheTypeOfEveryOperatorItCovers(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $left = new Variable('a');
        $right = new Variable('b');

        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\BinaryOp\BooleanAnd($left, $right)));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\BinaryOp\BooleanOr($left, $right)));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\BinaryOp\LogicalAnd($left, $right)));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\BinaryOp\LogicalOr($left, $right)));
        self::assertSame('bool', $expressions->resultType(new BooleanNot($left)));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\Isset_([$left])));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\Empty_($left)));
        self::assertSame('bool', $expressions->resultType(new \PhpParser\Node\Expr\Instanceof_($left, new \PhpParser\Node\Name('C'))));
        self::assertSame('int', $expressions->resultType(new \PhpParser\Node\Expr\PostInc($left)));
        self::assertSame('int', $expressions->resultType(new \PhpParser\Node\Expr\PreInc($left)));
        self::assertSame('int', $expressions->resultType(new \PhpParser\Node\Expr\PostDec($left)));
        self::assertSame('int', $expressions->resultType(new \PhpParser\Node\Expr\PreDec($left)));
        self::assertNull($expressions->resultType($left));
    }

    public function testEvaluateOperandsReachesAStatementWrittenInsideAnOperand(): void
    {
        $catalog = (new \SqlCatalog\Analyzer())->analyzeSource([
            't.php' => '<?php function f(PDO $d, bool $on): void { $on && $d->query("SELECT 1"); }',
        ]);

        self::assertSame('SELECT 1', $catalog->entries()[0]->sql());
    }

    public function testEvaluateOperandsReachesAStatementNestedSeveralOperatorsDeep(): void
    {
        $catalog = (new \SqlCatalog\Analyzer())->analyzeSource([
            't.php' => '<?php function f(PDO $d, bool $a, bool $b): void { $a && ($b || $d->query("SELECT 2")); }',
        ]);

        self::assertSame('SELECT 2', $catalog->entries()[0]->sql());
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
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

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
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('a{$}c', $environment->read('result')->patterns()[0]->display());
    }

    public function testEvaluateTernaryHandlesTheShortForm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = "a" ?: "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

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
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateAppendStartsFromWhatTheVariableAlreadyHolds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $a .= "y"; $a .= "z";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $paths = new PathSet();
        $expressions->bodies()->walk($file->statements, $paths, new FunctionScope('t.php'));
        $environment = $paths->join();

        self::assertSame('xyz', $environment->read('a')->soleLiteral()?->value);
    }
}
