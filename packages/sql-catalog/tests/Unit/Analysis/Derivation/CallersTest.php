<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\CallerIndex;
use SqlCatalog\Analysis\Derivation\Callers;
use SqlCatalog\Analysis\Derivation\Deriver;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\SinkMatcher;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;

#[CoversClass(Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(CallerIndex::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\FunctionShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
final class CallersTest extends TestCase
{
    public function testOfFindsTheCallsWrittenWithAFunctionsName(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; function run($sql) {} run("a"); \\App\\run("b"); \\Other\\run("c"); $o->run("d");');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Function_::class);
        self::assertInstanceOf(Stmt\Function_::class, $function);

        $callers = (new Callers(new CallerIndex([$file]), $index))->of($function, 0, $deriver);

        self::assertSame(['run("a")', '\\App\\run("b")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers->calls));
    }

    public function testOfFindsTheInstantiationsOfAClassForItsConstructor(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace App; class Repo { public function __Construct($sql) {} }'
            . ' new Repo("a"); new \\App\\Repo("b"); new \\Other\\Repo("c"); new Other("d"); new $class("e");',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $constructor = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $constructor);

        $callers = (new Callers(new CallerIndex([$file]), $index, new SinkMatcher((new PdoExtension())->sinks(), $index)))->of($constructor, 0, $deriver);

        self::assertSame(['new \\App\\Repo("a")', 'new \\App\\Repo("b")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers->calls));
    }

    public function testOfKeepsTheCallsThatCanReachTheMethodInTheOrderWritten(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace App;'
            . ' class Repo { public function find($id) { return $this->find($id); } }'
            . ' class Other { public function find($id) { return $id; } public function run() { return $this->find(1); } }'
            . ' $repo = new Repo(); $repo->find(2); $repo?->find(3); Repo::find(4); Other::find(5); (new Other())->find(6); find(7);',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);

        $callers = (new Callers(new CallerIndex([$file]), $index, new SinkMatcher((new PdoExtension())->sinks(), $index)))->of($method, 0, $deriver);

        self::assertSame(
            ['$this->find($id)', '$repo->find(2)', '$repo?->find(3)', '\\App\\Repo::find(4)'],
            array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers->calls),
        );
    }

    public function testOfSkipsClassesAnExtensionModels(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Db extends PDO { public function run($sql) { return $this->query($sql); } }'
            . ' $db = new Db("sqlite::memory:"); $db->run("SELECT 1");',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);

        $modeled = (new Callers(new CallerIndex([$file]), $index, new SinkMatcher((new PdoExtension())->sinks(), $index)))->of($method, 0, $deriver);
        $unmodeled = (new Callers(new CallerIndex([$file]), $index))->of($method, 0, $deriver);

        self::assertSame([], $modeled->calls);
        self::assertSame(['$db->run("SELECT 1")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $unmodeled->calls));
    }

    public function testOfFindsNoCallersOfAClosure(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $f = function ($sql) { return $sql; }; $f("a"); $g = fn ($sql) => $sql; $g("b");');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $closure = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\Closure::class);
        self::assertInstanceOf(Expr\Closure::class, $closure);
        $arrow = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\ArrowFunction::class);
        self::assertInstanceOf(Expr\ArrowFunction::class, $arrow);
        $callers = new Callers(new CallerIndex([$file]), $index);

        self::assertSame([], $callers->of($closure, 0, $deriver)->calls);
        self::assertSame([], $callers->of($arrow, 0, $deriver)->calls);
    }

    public function testOfFindsNoCallersOfAMethodWrittenOutsideAnyClass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class Repo { public function find($id) {} } $repo = new Repo(); $repo->find(1); Repo::find(2);');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);

        $callers = (new Callers(new CallerIndex([$file]), $index, new SinkMatcher((new PdoExtension())->sinks(), $index)))->of(new Stmt\ClassMethod('find'), 0, $deriver);

        self::assertSame([], $callers->calls);
    }

    public function testOfListsEveryCallerWithoutStoppingAtTheCallerLimit(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function run($sql) {} ' . str_repeat('run("SELECT 1"); ', Deriver::MAX_CALLERS + 1));
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Function_::class);
        self::assertInstanceOf(Stmt\Function_::class, $function);

        self::assertCount(Deriver::MAX_CALLERS + 1, (new Callers(new CallerIndex([$file]), $index))->of($function, 0, $deriver)->calls);
    }

    public function testOfMarksTheCallersPartialWhenACallMightReachTheMethodWithoutThatBeingCertain(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Repo { public function find($id) {} } class Other { public function find($id) {} }'
            . ' $repo = new Repo(); $repo->find(1); $unknown->find(2);',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);

        $callers = (new Callers(new CallerIndex([$file]), $index))->of($method, 0, $deriver);

        self::assertSame(['$repo->find(1)'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers->calls));
        self::assertTrue($callers->partial);
    }

    public function testOfIsNotPartialWhenEveryCallIsAccountedFor(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Repo { public function find($id) {} } class Other { public function find($id) {} }'
            . ' $repo = new Repo(); $repo->find(1); (new Other())->find(2);',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);

        $callers = (new Callers(new CallerIndex([$file]), $index))->of($method, 0, $deriver);

        self::assertCount(1, $callers->calls);
        self::assertFalse($callers->partial);
    }

    public function testOfFindsTheExplicitCallsOfAConstructorAsWellAsTheInstantiations(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            "<?php class Base { public function __construct(\$sql) {} }\n"
            . "class Child extends Base { public function __construct() { parent::__construct('a'); } }\n"
            . "class Again extends Base { public function reset() { \$this->__construct('b'); self::__construct('c'); } }\n"
            . "new Base('d'); new Again('e'); new Child();",
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $constructor = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $constructor);

        $callers = (new Callers(new CallerIndex([$file]), $index))->of($constructor, 0, $deriver);

        self::assertSame(
            ["new \\Base('d')", "new \\Again('e')", "parent::__construct('a')", "\$this->__construct('b')", "self::__construct('c')"],
            array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers->calls),
        );
        self::assertFalse($callers->partial);
    }

    public function testOfFunctionMatchesTheGlobalFunctionACallInANamespaceFallsBackTo(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace { function run($sql) {} } namespace App { run("a"); RUN("b"); \\run("c"); \\App\\run("d"); App\\run("e"); }',
        );
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Function_::class);
        self::assertInstanceOf(Stmt\Function_::class, $function);

        $callers = (new Callers(new CallerIndex([$file]), new ProgramIndex()))->ofFunction($function);

        self::assertSame(['run("a")', 'RUN("b")', '\\run("c")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers));
    }

    public function testOfFunctionLeavesOutCallsOfAnotherFunctionWithTheSameShortName(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace App { function run($sql) {} \\App\\run("a"); \\Other\\run("b"); $o->run("c"); Repo::run("d"); }'
            . ' namespace { run("e"); \\APP\\RUN("f"); }',
        );
        $function = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\Function_::class);
        self::assertInstanceOf(Stmt\Function_::class, $function);

        $callers = (new Callers(new CallerIndex([$file]), new ProgramIndex()))->ofFunction($function);

        self::assertSame(['\\App\\run("a")', '\\APP\\RUN("f")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers));
    }

    public function testOfFunctionFallsBackToTheWrittenNameOfADeclarationWithoutAResolvedOne(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php run("a"); \\Other\\run("b");');

        $function = new Stmt\Function_('run');
        $function->namespacedName = null;

        $callers = (new Callers(new CallerIndex([$file]), new ProgramIndex()))->ofFunction($function);

        self::assertSame(['\\run("a")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $callers));
    }

    public function testInstantiationsMatchTheClassEachNewIsWrittenWith(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php namespace App; class Repo {} new Repo("a"); new \\Other\\Repo("b"); new \\APP\\REPO("c"); new $class("d");',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $callers = new Callers(new CallerIndex([$file]), $index);

        $plain = $callers->instantiations('App\\Repo', $deriver);
        $qualified = $callers->instantiations('\\app\\repo', $deriver);

        self::assertSame(['new \\App\\Repo("a")', 'new \\APP\\REPO("c")'], array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $plain));
        self::assertSame($plain, $qualified);
    }

    public function testInstantiationsIncludeSubclassesThatInheritTheConstructor(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Base { public function __construct($sql) {} } class Plain extends Base {} class Deeper extends Plain {}'
            . ' class Own extends Base { public function __construct() {} } new Base("a"); new Plain("b"); new Deeper("c"); new Own();',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);

        $calls = (new Callers(new CallerIndex([$file]), $index))->instantiations('Base', $deriver);

        self::assertSame(
            ['new \\Base("a")', 'new \\Plain("b")', 'new \\Deeper("c")'],
            array_map(static fn (Expr\CallLike $call): string => (new Standard())->prettyPrintExpr($call), $calls),
        );
    }

    public function testInstantiationsReadSelfAndStaticAsTheClassTheyAreWrittenIn(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Repo { public static function make() { return [new SELF(), new static()]; } } $a = new self(); $b = new static();',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $callers = new Callers(new CallerIndex([$file]), $index);

        self::assertSame([], $callers->instantiations('SELF', $deriver));
        self::assertSame([], $callers->instantiations('self', $deriver));
        self::assertSame([], $callers->instantiations('static', $deriver));
    }

    /**
     * @return array<string, array{string, string, bool|null}>
     */
    public static function providerReaches(): array
    {
        return [
            'static call on the class' => ["<?php\nRepo::find();\nclass Repo { public function find() {} }", 'Repo', true],
            'static call on a subclass' => ["<?php\nChild::find();\nclass Repo { public function find() {} } class Child extends Repo {}", 'Repo', true],
            'static call on a parent class' => ["<?php\nRepo::find();\nclass Repo {} class Child extends Repo { public function find() {} }", 'Child', false],
            'static call on self does not reach an override' => ["<?php class Repo { public function a() {\nself::find(); } public function find() {} } class Child extends Repo { public function find() {} }", 'Child', false],
            'static call on static reaches an override' => ["<?php class Repo { public function a() {\nstatic::find(); } public function find() {} } class Child extends Repo { public function find() {} }", 'Child', true],
            'static call on parent does not reach the class it is written in' => ["<?php class Repo { public function find() {} } class Child extends Repo { public function find() {\nparent::find(); } }", 'Child', false],
            'static call on a parent outside the analyzed files' => ["<?php class Child extends Missing { public function find() {\nparent::find(); } }", 'Child', false],
            'static call on parent in a class without one' => ["<?php class Repo { public function find() {\nparent::find(); } }", 'Repo', false],
            'static call on an unrelated class' => ["<?php\nOther::find();\nclass Repo { public function find() {} } class Other {}", 'Repo', false],
            'static call on self' => ["<?php class Repo { public function a() {\nself::find(); } public function find() {} }", 'Repo', true],
            'static call on static' => ["<?php class Repo { public function a() {\nSTATIC::find(); } public function find() {} }", 'Repo', true],
            'static call on parent' => ["<?php class Repo { public function find() {} } class Child extends Repo { public function find() {\nparent::find(); } }", 'Repo', true],
            'static call on self in an unrelated class' => ["<?php class Other { public function a() {\nself::find(); } public function find() {} } class Repo { public function find() {} }", 'Repo', false],
            'static call on self outside a class' => ["<?php\nself::find();\nclass Repo { public function find() {} }", 'Repo', false],
            'static call on a class held in a variable' => ["<?php\n\$class::find();\nclass Repo { public function find() {} }", 'Repo', null],
            'function call' => ["<?php\nfind();\nclass Repo { public function find() {} }", 'Repo', false],
            'call on $this in the class' => ["<?php class Repo { public function a() {\n\$this->find(); } public function find() {} }", 'Repo', true],
            'call on $this in a subclass' => ["<?php class Repo { public function find() {} } class Child extends Repo { public function a() {\n\$this->find(); } }", 'Repo', true],
            'call on $this in a subclass that overrides the method' => ["<?php class Repo { public function find() {} } class Child extends Repo { public function find() {} public function a() {\n\$this->find(); } }", 'Repo', false],
            'call on $this in a class whose subclass overrides the method' => ["<?php class Repo { public function a() {\n\$this->find(); } public function find() {} } class Child extends Repo { public function find() {} }", 'Child', true],
            'call on $this in an unrelated class' => ["<?php class Other { public function a() {\n\$this->find(); } public function find() {} } class Repo { public function find() {} }", 'Repo', false],
            'call on $this outside a class' => ["<?php\n\$this->find();\nclass Repo { public function find() {} }", 'Repo', false],
            'call on an instance of the class' => ["<?php \$repo = new Repo();\n\$repo->find();\nclass Repo { public function find() {} }", 'Repo', true],
            'nullsafe call on an instance of the class' => ["<?php \$repo = new Repo();\n\$repo?->find();\nclass Repo { public function find() {} }", 'Repo', true],
            'call on an instance of an unrelated class declaring the method' => ["<?php \$other = new Other();\n\$other->find();\nclass Repo { public function find() {} } class Other { public function find() {} }", 'Repo', false],
            'call on an instance of an unrelated class not declaring the method' => ["<?php \$other = new Other();\n\$other->find();\nclass Repo { public function find() {} } class Other {}", 'Repo', false],
            'call on one of several classes' => ["<?php \$x = \$c ? new Other() : new Repo();\n\$x->find();\nclass Repo { public function find() {} } class Other { public function find() {} }", 'Repo', true],
            'call on something unknown when only the hierarchy declares the method' => ["<?php\n\$unknown->find();\nclass Repo { public function find() {} } class Child extends Repo { public function find() {} }", 'Repo', null],
            'call on something unknown when another class declares the method' => ["<?php\n\$unknown->find();\nclass Repo { public function find() {} } class Other { public function find() {} }", 'Repo', null],
            'call on a built-in object with a method of the same name' => ["<?php \$d = new DateTime();\n\$d->format('Y');\nclass Report { public function format() {} }", 'Report', false],
        ];
    }

    #[DataProvider('providerReaches')]
    public function testReachesDecidesWhetherACallCanLandInTheMethod(string $code, string $className, ?bool $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $call = (new NodeFinder())->findFirst(
            $file->statements,
            static fn (Node $node): bool => $node instanceof Expr\CallLike && !$node instanceof Expr\New_ && $node->getStartLine() === 2,
        );
        self::assertInstanceOf(Expr\CallLike::class, $call);

        self::assertSame($expected, (new Callers(new CallerIndex([$file]), $index))->reaches($call, $className, 0, $deriver));
    }

    public function testReachesWorksOutTheReceiverOneCallDeeper(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            "<?php function useIt(\$r) {\n\$r->find(); }\nuseIt(new Other());\nclass Repo { public function find() {} } class Other {}",
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, [], new EvaluationBudget(maxDepth: 4)))->deriverFor([$file]);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        $callers = new Callers(new CallerIndex([$file]), $index);

        self::assertFalse($callers->reaches($call, 'Repo', 2, $deriver));
        self::assertNull($callers->reaches($call, 'Repo', 3, $deriver));
    }

    public function testReachesCannotTellOnceTheBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse('t.php', "<?php \$other = new Other();\n\$other->find();\nclass Repo { public function find() {} } class Other {}");
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(maxSteps: 0);
        $deriver = (new Interpreter($index, [], $budget))->deriverFor([$file]);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\MethodCall::class);
        self::assertInstanceOf(Expr\MethodCall::class, $call);
        self::assertFalse($budget->spend());

        self::assertNull((new Callers(new CallerIndex([$file]), $index))->reaches($call, 'Repo', 0, $deriver));
    }

    public function testRelatedHoldsBetweenAClassAndItsHierarchyInEitherDirection(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class Repo { function find() {} } class Child extends Repo {} class Other {}');
        $callers = new Callers(new CallerIndex(), (new ProgramIndexBuilder())->build([$file]));

        self::assertTrue($callers->related('Repo', 'Repo', 'find'));
        self::assertTrue($callers->related('Child', 'Repo', 'find'));
        self::assertTrue($callers->related('repo', 'CHILD', 'find'));
        self::assertFalse($callers->related('Other', 'Repo', 'find'));
        self::assertFalse($callers->related('Repo', 'Other', 'find'));
        self::assertFalse($callers->related('DateTime', 'Repo', 'format'));
    }

    public function testRelatedStopsAtAnOverrideBetweenTheReceiverAndTheDeclaringClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Repo { function find() {} function all() {} } class Middle extends Repo { function FIND() {} } class Child extends Middle {}'
            . ' class A extends B {} class B extends A {}',
        );
        $callers = new Callers(new CallerIndex(), (new ProgramIndexBuilder())->build([$file]));

        self::assertFalse($callers->related('Child', 'Repo', 'find'));
        self::assertFalse($callers->related('Middle', 'Repo', 'Find'));
        self::assertTrue($callers->related('Child', 'Middle', 'find'));
        self::assertTrue($callers->related('Child', 'Repo', 'all'));
        self::assertTrue($callers->related('Repo', 'Middle', 'find'));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function providerStaticTarget(): array
    {
        return [
            'a class name' => ["<?php class Repo { public function a() {\nOther::find(); } }", 'Other'],
            'self' => ["<?php class Repo { public function a() {\nSELF::find(); } }", 'Repo'],
            'static' => ["<?php class Repo { public function a() {\nstatic::find(); } }", 'Repo'],
            'parent' => ["<?php class Base {} class Repo extends Base { public function a() {\nParent::find(); } }", 'Base'],
            'parent of a class that extends nothing' => ["<?php class Repo { public function a() {\nparent::find(); } }", null],
            'self outside a class' => ["<?php\nself::find();", null],
            'a class held in a variable' => ["<?php\n\$class::find();", null],
        ];
    }

    #[DataProvider('providerStaticTarget')]
    public function testStaticTargetIsTheClassTheLookupStartsFrom(string $code, ?string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\StaticCall::class);
        self::assertInstanceOf(Expr\StaticCall::class, $call);

        self::assertSame($expected, (new Callers(new CallerIndex([$file]), $index))->staticTarget($call, $deriver));
    }

    public function testInheritorsOfListsTheSubclassesThatInheritTheConstructor(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class Base { public function __construct() {} } class Plain extends Base {} class Deeper extends Plain {}'
            . ' class Own extends Base { public function __construct() {} } class Below extends Own {} class Loose {}'
            . ' class A extends B {} class B extends A {}',
        );
        $callers = new Callers(new CallerIndex(), (new ProgramIndexBuilder())->build([$file]));

        self::assertSame(['\\Base', 'Plain', 'Deeper'], $callers->inheritorsOf('\\Base'));
        self::assertSame(['Own', 'Below'], $callers->inheritorsOf('Own'));
        self::assertSame(['A'], $callers->inheritorsOf('A'));
    }
}
