<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Analysis\Derivation\SliceExecutor;
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
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class ExpressionEvaluatorTest extends TestCase
{
    #[DataProvider('providerEvaluate')]
    public function testEvaluate(string $expression, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = ' . $expression . ';');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

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
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Scalar\InterpolatedString::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $result = $expressions->evaluateInterpolation($node, new Environment(), new FunctionScope('t.php'));

        self::assertSame('a{$}c', $result->patterns()[0]->display());
    }

    public function testEvaluateTernaryIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $c ? "a" : "b";');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Ternary::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        self::assertCount(2, $expressions->evaluateTernary($node, new Environment(), new FunctionScope('t.php'))->terms);
    }

    public function testEvaluateAssignIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x";');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Assign::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $result = $expressions->evaluateAssign($node, $environment, new FunctionScope('t.php'));

        self::assertSame('x', $result->soleLiteral()?->value);
        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateAppendIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a .= "y";');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\AssignOp\Concat::class, $node);

        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Evaluation\Domain::literal('x')]);
        $result = $expressions->evaluateAppend($node, $environment, new FunctionScope('t.php'));

        self::assertSame('xy', $result->soleLiteral()?->value);
    }

    public function testEvaluateOfAnInterpolatedStringJoinsItsParts(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $t = "users"; $result = "SELECT * FROM {$t}";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('SELECT * FROM users', $environment->read('result')->soleLiteral()?->value);
    }

    public function testEvaluateOfATernaryKeepsBothBranches(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = $c ? "a" : "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertCount(2, $environment->read('result')->terms);
    }

    /**
     * @param array<string, \SqlCatalog\Evaluation\Domain> $bindings
     */
    #[DataProvider('providerIsset')]
    public function testEvaluateIssetRespectsDefinednessAndNullability(string $code, array $bindings, string $function, ?bool $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ' . $code . ';');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $result = $expressions->evaluate($statement->expr, new Environment($bindings), new FunctionScope('t.php', $function));

        self::assertSame('bool', $result->type()->display());
        self::assertSame($expected, $result->soleLiteral()?->value);
    }

    /**
     * @return array<string, array{string, array<string, \SqlCatalog\Evaluation\Domain>, string, bool|null}>
     */
    public static function providerIsset(): array
    {
        $literal = \SqlCatalog\Evaluation\Domain::literal(...);
        $unknown = \SqlCatalog\Evaluation\Domain::unknown();
        $nullable = \SqlCatalog\Evaluation\Domain::opaque(\SqlCatalog\Type\TypeShape::of(['string', 'null']), Origin::Parameter);

        return [
            'undefined local' => ['isset($a)', [], 'f', false],
            'unbound file variable stays open' => ['isset($a)', [], FunctionScope::MAIN, null],
            'null' => ['isset($a)', ['a' => $literal(null)], 'f', false],
            'string' => ['isset($a)', ['a' => $literal(' WHERE active = 1')], 'f', true],
            'empty string' => ['isset($a)', ['a' => $literal('')], 'f', true],
            'false' => ['isset($a)', ['a' => $literal(false)], 'f', true],
            'zero' => ['isset($a)', ['a' => $literal(0)], 'f', true],
            'null and string alternatives' => ['isset($a)', ['a' => $literal(null)->union($literal('x'))], 'f', null],
            'unknown value' => ['isset($a)', ['a' => $unknown], 'f', null],
            'nullable parameter' => ['isset($a)', ['a' => $nullable], 'f', null],
            'all variables set' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $literal('b')], 'f', true],
            'second variable null' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $literal(null)], 'f', false],
            'unknown then null' => ['isset($a, $b)', ['a' => $unknown, 'b' => $literal(null)], 'f', false],
            'set then unknown' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $unknown], 'f', null],
            'array element remains open' => ['isset($a["key"])', ['a' => $unknown], 'f', null],
            'property remains open' => ['isset($a->p)', ['a' => $unknown], 'f', null],
            'superglobal remains external' => ['isset($_GET)', [], 'f', null],
            'this outside a class remains open' => ['isset($this)', [], 'f', null],
            'dynamic variable remains open' => ['isset($$a)', ['a' => $literal('b')], 'f', null],
        ];
    }

    public function testEvaluateIssetStopsAfterAVariableKnownToBeNull(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php isset($a, $items[$key = "later"]);');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Evaluation\Domain::literal(null)]);

        $result = $expressions->evaluate($statement->expr, $environment, new FunctionScope('t.php', 'f'));

        self::assertFalse($result->soleLiteral()?->value);
        self::assertFalse($environment->has('key'));
    }

    #[DataProvider('providerKnownTernaryCondition')]
    public function testEvaluateTernaryRunsOnlyTheSelectedBranch(string $code, string|bool $expected, ?string $assigned): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ' . $code . ';');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Evaluation\Domain::literal('value')]);

        $result = $expressions->evaluate($statement->expr, $environment, new FunctionScope('t.php', 'f'));

        self::assertSame($expected, $result->soleLiteral()?->value);
        self::assertSame($assigned, $environment->read('chosen')->soleLiteral()?->value);
    }

    /**
     * @return array<string, array{string, string|bool, string|null}>
     */
    public static function providerKnownTernaryCondition(): array
    {
        return [
            'set' => ['isset($a) ? ($chosen = "yes") : ($chosen = "no")', 'yes', 'yes'],
            'unset' => ['isset($missing) ? ($chosen = "yes") : ($chosen = "no")', 'no', 'no'],
            'short set' => ['isset($a) ?: ($chosen = "no")', true, null],
            'short unset' => ['isset($missing) ?: ($chosen = "no")', 'no', 'no'],
        ];
    }

    public function testEvaluateOfAMatchKeepsEveryArm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = match ($c) { 1 => "a", default => "b" };');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateOfAnAppendingAssignmentExtendsTheVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $sql = "SELECT 1"; $sql .= " FROM t";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('SELECT 1 FROM t', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateGivesUpOnceTheBudgetIsSpent(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), [], new \SqlCatalog\Analysis\EvaluationBudget(0)))
            ->evaluatorFor();
        $result = $expressions->evaluate(new String_('SELECT 1'), new Environment(), new FunctionScope('t.php'));
        self::assertFalse($result->isExact());
    }

    public function testReferencesIsTheReaderThisEvaluatorResolvesNamesWith(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        self::assertSame($expressions->references(), $expressions->references());
    }

    public function testEvaluateScalarOnlyAnswersForLiterals(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        self::assertSame('a', $expressions->evaluateScalar(new String_('a'))?->soleLiteral()?->value);
        self::assertSame(1, $expressions->evaluateScalar(new Int_(1))?->soleLiteral()?->value);
        self::assertSame(1.5, $expressions->evaluateScalar(new Float_(1.5))?->soleLiteral()?->value);
        self::assertNull($expressions->evaluateScalar(new Variable('a')));
    }

    public function testEvaluateOperatorOnlyAnswersForOperators(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $scope = new FunctionScope('t.php');
        self::assertNull($expressions->evaluateOperator(new Variable('a'), $environment, $scope));
        self::assertNotNull($expressions->evaluateOperator(new BooleanNot(new Variable('a')), $environment, $scope));
    }

    public function testEvaluateResultReportsTheTypeTheOperatorProduces(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $scope = new FunctionScope('t.php');

        self::assertSame('bool', $expressions->evaluateResult(new BooleanNot(new Variable('a')), $environment, $scope)?->type()->display());
        self::assertNull($expressions->evaluateResult(new Variable('a'), $environment, $scope));
    }

    public function testResultTypeNamesTheTypeOfEveryOperatorItCovers(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
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
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $cast = new Cast\Object_(new Variable('a'));
        $result = $expressions->evaluateCast($cast, new Environment(), new FunctionScope('t.php'));
        self::assertSame('object', $result->type()->display());
    }

    public function testEvaluateCastEndsTheTrailBackToExternalInput(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = (int) $_GET["id"];');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        $holes = $environment->read('result')->patterns()[0]->holes();
        self::assertSame(Origin::Call, $holes[0]->origin);
    }

    public function testCastLiteralWritesTheValueAsTheTypeItIsCastTo(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        self::assertSame(12, $expressions->castLiteral('12', 'int')->soleLiteral()?->value);
        self::assertSame(1.5, $expressions->castLiteral('1.5', 'float')->soleLiteral()?->value);
        self::assertTrue($expressions->castLiteral(1, 'bool')->soleLiteral()?->value);
    }

    public function testEvaluateInterpolationJoinsLiteralPartsAndExpressions(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = "a{$b}c";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('a{$}c', $environment->read('result')->patterns()[0]->display());
    }

    public function testEvaluateTernaryHandlesTheShortForm(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = "a" ?: "b";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertCount(2, $environment->read('result')->terms);
    }

    public function testEvaluateMatchOfNoArmsKnowsNothing(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $match = new \PhpParser\Node\Expr\Match_(new Variable('a'), []);
        $result = $expressions->evaluateMatch($match, new Environment(), new FunctionScope('t.php'));
        self::assertSame('mixed', $result->type()->display());
    }

    public function testEvaluateAssignWritesIntoTheEnvironment(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateAppendStartsFromWhatTheVariableAlreadyHolds(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a = "x"; $a .= "y"; $a .= "z";');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('xyz', $environment->read('a')->soleLiteral()?->value);
    }

    public function testEvaluateSeesAnAssignmentWrittenInsideAnOperatorItDoesNotModel(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ($sql = "SELECT 1") + 1;');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $expressions->evaluate($statement->expr, $environment, new FunctionScope('t.php'));

        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateOperandsEvaluatesEveryOperandInPlace(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ($sql = "SELECT 1") - f($table = "users");');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $expressions->evaluateOperands($statement->expr, $environment, new FunctionScope('t.php'));

        self::assertSame(['sql', 'table'], $environment->names());
        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateOperatorOfACoalesceKeepsBothSides(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $a ?? "b";');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Evaluation\Domain::literal('a')]);

        $result = $expressions->evaluateOperator($statement->expr, $environment, new FunctionScope('t.php'));

        self::assertSame(['a', 'b'], array_map(
            static fn (\SqlCatalog\Text\TextPattern $pattern): string => $pattern->display(),
            $result?->patterns() ?? [],
        ));
    }

    public function testEvaluateCastNamesTheTypeACastProducesWhenTheValueIsNotKnown(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $scope = new FunctionScope('t.php');

        $array = $expressions->evaluateCast(new Cast\Array_(new Variable('a')), $environment, $scope);
        $int = $expressions->evaluateCast(new Cast\Int_(new Variable('a')), $environment, $scope);

        self::assertSame('array', $array->type()->display());
        self::assertSame('(array) cast', $array->patterns()[0]->holes()[0]->expression);
        self::assertSame('int', $int->type()->display());
        self::assertSame('(int) cast', $int->patterns()[0]->holes()[0]->expression);
    }

    public function testEvaluateResultSeesAnAssignmentWrittenInsideTheOperand(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php !($sql = "SELECT 1");');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $expressions->evaluateResult($statement->expr, $environment, new FunctionScope('t.php'));

        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateMatchSeesAssignmentsInTheSubjectAndInTheConditions(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php match ($kind = "a") { ($table = "users") => 1, default => 2 };');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Match_::class, $node);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $expressions->evaluateMatch($node, $environment, new FunctionScope('t.php'));

        self::assertSame('a', $environment->read('kind')->soleLiteral()?->value);
        self::assertSame('users', $environment->read('table')->soleLiteral()?->value);
    }
}
