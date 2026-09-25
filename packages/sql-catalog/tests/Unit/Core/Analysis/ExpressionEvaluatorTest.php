<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

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
use SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Analysis\StatementRecorder;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\SourceParser;
use SqlCatalog\Core\Text\Origin;

#[CoversClass(ExpressionEvaluator::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(Environment::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayEntry::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Facade\AnalysisOptions::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EntryFactory::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\QueryRecord::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Facade\Analyzer::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\CallSite::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Catalog::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\EntryIdentity::class)]
#[UsesClass(\SqlCatalog\Core\Catalog\Resolution::class)]
#[UsesClass(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class)]
#[UsesClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Mysqli\MysqliExtension::class)]
#[UsesClass(\SqlCatalog\Extension\Pdo\PdoExtension::class)]
#[UsesClass(\SqlCatalog\Core\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Core\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndexBuilder::class)]
#[UsesClass(\SqlCatalog\Core\Source\SourceFile::class)]
#[UsesClass(\SqlCatalog\Core\Sql\PlaceholderScanner::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlLexer::class)]
#[UsesClass(\SqlCatalog\Core\Sql\SqlToken::class)]
#[UsesClass(\SqlCatalog\Core\Sql\StatementKindReader::class)]
#[UsesClass(\SqlCatalog\Core\Sql\TableReader::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
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
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\CallContext::class)]
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
        $environment = new Environment(['a' => \SqlCatalog\Core\Evaluation\Domain::literal('x')]);
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
     * @param array<string, \SqlCatalog\Core\Evaluation\Domain> $bindings
     */
    #[DataProvider('providerIsset')]
    public function testEvaluateIssetDoesNotEvaluatePredicates(string $code, array $bindings, string $function): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ' . $code . ';');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        $result = $expressions->evaluate($statement->expr, new Environment($bindings), new FunctionScope('t.php', $function));

        self::assertSame('bool', $result->type()->display());
        self::assertNull($result->soleLiteral());
    }

    /**
     * @return array<string, array{string, array<string, \SqlCatalog\Core\Evaluation\Domain>, string}>
     */
    public static function providerIsset(): array
    {
        $literal = \SqlCatalog\Core\Evaluation\Domain::literal(...);
        $unknown = \SqlCatalog\Core\Evaluation\Domain::unknown();
        $nullable = \SqlCatalog\Core\Evaluation\Domain::opaque(\SqlCatalog\Core\Type\TypeShape::of(['string', 'null']), Origin::Parameter);

        return [
            'undefined local' => ['isset($a)', [], 'f'],
            'unbound file variable stays open' => ['isset($a)', [], FunctionScope::MAIN],
            'null' => ['isset($a)', ['a' => $literal(null)], 'f'],
            'string' => ['isset($a)', ['a' => $literal(' WHERE active = 1')], 'f'],
            'empty string' => ['isset($a)', ['a' => $literal('')], 'f'],
            'false' => ['isset($a)', ['a' => $literal(false)], 'f'],
            'zero' => ['isset($a)', ['a' => $literal(0)], 'f'],
            'null and string alternatives' => ['isset($a)', ['a' => $literal(null)->union($literal('x'))], 'f'],
            'unknown value' => ['isset($a)', ['a' => $unknown], 'f'],
            'nullable parameter' => ['isset($a)', ['a' => $nullable], 'f'],
            'all variables set' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $literal('b')], 'f'],
            'second variable null' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $literal(null)], 'f'],
            'unknown then null' => ['isset($a, $b)', ['a' => $unknown, 'b' => $literal(null)], 'f'],
            'set then unknown' => ['isset($a, $b)', ['a' => $literal('a'), 'b' => $unknown], 'f'],
            'array element remains open' => ['isset($a["key"])', ['a' => $unknown], 'f'],
            'property remains open' => ['isset($a->p)', ['a' => $unknown], 'f'],
            'superglobal remains external' => ['isset($_GET)', [], 'f'],
            'this outside a class remains open' => ['isset($this)', [], 'f'],
            'dynamic variable remains open' => ['isset($$a)', ['a' => $literal('b')], 'f'],
        ];
    }

    public function testEvaluateIssetKeepsPossibleOperandEffects(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php isset($a, $items[$key = "later"]);');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Core\Evaluation\Domain::literal(null)]);

        $result = $expressions->evaluate($statement->expr, $environment, new FunctionScope('t.php', 'f'));

        self::assertNull($result->soleLiteral());
        self::assertCount(2, $environment->read('key')->terms);
    }

    #[DataProvider('providerKnownTernaryCondition')]
    public function testEvaluateTernaryKeepsBothBranchesEvenForKnownConditions(string $code): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ' . $code . ';');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['a' => \SqlCatalog\Core\Evaluation\Domain::literal('value')]);

        $result = $expressions->evaluate($statement->expr, $environment, new FunctionScope('t.php', 'f'));

        self::assertCount(2, $result->terms);
        self::assertCount(2, $environment->read('chosen')->terms);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerKnownTernaryCondition(): array
    {
        return [
            'true' => ['true ? ($chosen = "yes") : ($chosen = "no")'],
            'false' => ['false ? ($chosen = "yes") : ($chosen = "no")'],
            'set' => ['isset($a) ? ($chosen = "yes") : ($chosen = "no")'],
            'unset' => ['isset($missing) ? ($chosen = "yes") : ($chosen = "no")'],
            'short set' => ['isset($a) ?: ($chosen = "no")'],
            'short unset' => ['isset($missing) ?: ($chosen = "no")'],
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
        $expressions = (new Interpreter(new ProgramIndex(), [], new \SqlCatalog\Core\Analysis\EvaluationBudget(0)))
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
        $catalog = (new \SqlCatalog\Facade\Analyzer())->analyzeSource([
            't.php' => '<?php function f(PDO $d, bool $on): void { $on && $d->query("SELECT 1"); }',
        ]);

        self::assertSame('SELECT 1', $catalog->entries()[0]->sql());
    }

    public function testEvaluateOperandsReachesAStatementNestedSeveralOperatorsDeep(): void
    {
        $catalog = (new \SqlCatalog\Facade\Analyzer())->analyzeSource([
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
        $environment = new Environment(['a' => \SqlCatalog\Core\Evaluation\Domain::literal('a')]);

        $result = $expressions->evaluateOperator($statement->expr, $environment, new FunctionScope('t.php'));

        self::assertSame(['a', 'b'], array_map(
            static fn (\SqlCatalog\Core\Text\TextPattern $pattern): string => $pattern->display(),
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
        self::assertCount(2, $environment->read('table')->terms);
    }
    public function testEvaluateOptionalRightKeepsTheUnwrittenAlternative(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php true && ($tail = "suffix");')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(\PhpParser\Node\Expr\BinaryOp::class, $statement->expr);
        $environment = new Environment(['tail' => \SqlCatalog\Core\Evaluation\Domain::literal('before')]);
        $evaluator = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $evaluator->evaluateOptionalRight($statement->expr, $environment, new FunctionScope('a.php'));
        self::assertSame('literal:string:before|literal:string:suffix', $environment->read('tail')->signature());
    }


    public function testEvaluateTraversesIncludeEffectsBeforeReadingAnotherOperand(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php include "fragment.php";')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $environment = new Environment(['tail' => \SqlCatalog\Core\Evaluation\Domain::literal('old')]);
        $environment->markAbsent('missing');
        (new Interpreter(new ProgramIndex(), []))->evaluatorFor()->evaluate($statement->expr, $environment, new FunctionScope('a.php'));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Maybe, $environment->presence('missing'));
        self::assertNull($environment->read('tail')->soleLiteral());
    }

    public function testEvaluateIssetKeepsIntermediateOperandWrites(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php isset($items[$tail = "a"], $items[$tail = "b"]);')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(\PhpParser\Node\Expr\Isset_::class, $statement->expr);
        $environment = new Environment(['tail' => \SqlCatalog\Core\Evaluation\Domain::literal('before')]);
        $result = (new Interpreter(new ProgramIndex(), []))->evaluatorFor()->evaluateIsset($statement->expr, $environment, new FunctionScope('a.php'));
        self::assertNull($result->soleLiteral());
        self::assertSame('literal:string:a|literal:string:b|literal:string:before', $environment->read('tail')->signature());
    }

    public function testEvaluateMatchRetainsEarlierConditionEffectsForLaterArms(): void
    {
        $statement = (new SourceParser())->parse('a.php', '<?php match ($input) { ($tail = "changed") => "first", default => $tail };')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(\PhpParser\Node\Expr\Match_::class, $statement->expr);
        $environment = new Environment(['tail' => \SqlCatalog\Core\Evaluation\Domain::literal('before')]);
        $result = (new Interpreter(new ProgramIndex(), []))->evaluatorFor()->evaluateMatch($statement->expr, $environment, new FunctionScope('a.php'));
        self::assertSame('literal:string:before|literal:string:changed|literal:string:first', $result->signature());
    }


    public function testEvaluateCloneCreatesAnIndependentTrackedAllocation(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $object = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'original', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        $env = new Environment(['q' => \SqlCatalog\Core\Evaluation\Domain::of($object)]);
        $cloned = $expressions->evaluateClone(new \PhpParser\Node\Expr\Clone_(new Variable('q')), $env, new FunctionScope('query.php'))->soleObject();
        self::assertNotNull($cloned);
        self::assertNotSame($object->identity, $cloned->identity);
        self::assertSame($object->state, $cloned->state);
        self::assertSame($object, $env->read('q')->soleObject());
    }

    public function testEvaluateInvalidatesAllAliasesOfAReferenceAssignedObject(): void
    {
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $object = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        $env = new Environment(['q' => \SqlCatalog\Core\Evaluation\Domain::of($object), 'alias' => \SqlCatalog\Core\Evaluation\Domain::of($object)]);
        $expressions->evaluate(new \PhpParser\Node\Expr\AssignRef(new Variable('r'), new Variable('q')), $env, new FunctionScope('query.php'));
        self::assertNull($env->read('alias')->soleObject()?->state);
        self::assertSame('a', $env->read('alias')->soleObject()?->identity);
    }
}
