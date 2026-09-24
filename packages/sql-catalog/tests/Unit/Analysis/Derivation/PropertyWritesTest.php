<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use SqlCatalog\Php\ClassShape;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Php\ParsedFile;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(PropertyWrites::class)]
#[UsesClass(CallEvaluator::class)]
#[UsesClass(ConstantReader::class)]
#[UsesClass(Binding::class)]
#[UsesClass(CalleeReturns::class)]
#[UsesClass(CallerIndex::class)]
#[UsesClass(Callers::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(EntryBinder::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(Arrival::class)]
#[UsesClass(AssignmentSteps::class)]
#[UsesClass(BackwardSlicer::class)]
#[UsesClass(LoopPasses::class)]
#[UsesClass(Pending::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(Solution::class)]
#[UsesClass(SourceTree::class)]
#[UsesClass(EvaluationBudget::class)]
#[UsesClass(ExpressionEvaluator::class)]
#[UsesClass(ExternalInput::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(ReferenceEvaluator::class)]
#[UsesClass(SinkFinder::class)]
#[UsesClass(SinkMatcher::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(ClassShape::class)]
#[UsesClass(DeclaredGlobals::class)]
#[UsesClass(MethodShape::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(ParameterShape::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(TypeReader::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerSet::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\ReferenceEffects::class)]
final class PropertyWritesTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerValuesOf')]
    public function testValuesOfCollectsWhatEveryWriterLeaves(string $code, string $className, string $property, int $depth, array $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget();
        $tree = new SourceTree([$file]);
        $names = new FreeNames();
        $modified = new ModifiedNames($names);
        $writes = new PropertyWrites($index, $modified);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $deriver = new Deriver(
            $tree,
            new BackwardSlicer($tree, $budget, $names, $modified),
            new SliceExecutor(null, null, $modified, null, $budget),
            $expressions,
            new EntryBinder(new Callers(new CallerIndex([$file]), $index), $expressions, $budget, null, $writes),
            $budget,
            $names,
        );

        $values = $writes->valuesOf($className, $property, $depth, $deriver);

        self::assertSame($expected, array_map(static fn (Domain $value): string => $value->signature(), $values));
    }

    /**
     * @return array<string, array{string, string, string, int, list<string>}>
     */
    public static function providerValuesOf(): array
    {
        $writers = '<?php class C { private $t = "c";'
            . ' public function __construct() { $this->t = "a"; }'
            . ' public function set() { $this->t = "b"; }'
            . ' public function add() { $this->t = $this->t . "x"; } }';
        $assigned = '<?php class C { private $t; public function __construct($t) { $this->t = $t; } } new C("x");';
        $promoted = '<?php class C { public function __construct(private string $t) {} } new C("x"); new C("y");';

        return [
            'every writer in order, what reads the property being read left open, then the default' => [
                $writers,
                'C',
                't',
                0,
                ['literal:string:a', 'literal:string:b', "pattern:hole:property\x1ftext:x", 'literal:string:c'],
            ],
            'a class named in another case is read under the same guard' => [
                $writers,
                'c',
                't',
                0,
                ['literal:string:a', 'literal:string:b', "pattern:hole:property\x1ftext:x", 'literal:string:c'],
            ],
            'writers come before promotions' => [
                '<?php class C { public function __construct(private string $t) {} public function set() { $this->t = "b"; } } new C("x");',
                'C',
                't',
                0,
                ['literal:string:b', 'literal:string:x'],
            ],
            'a writer inherited from a parent' => [
                '<?php class P { public function init() { $this->t = "p"; } } class C extends P { public function set() { $this->t = "c"; } }',
                'C',
                't',
                0,
                ['literal:string:c', 'literal:string:p'],
            ],
            'the default is read in the class that declares it' => [
                '<?php class C { const X = "k"; private $t = self::X . "!"; }',
                'C',
                't',
                0,
                ['literal:string:k!'],
            ],
            'a writer one level short of the depth still reaches its callers' => [$assigned, 'C', 't', 2, ['literal:string:x']],
            'a writer at the depth limit stops at its parameter' => [$assigned, 'C', 't', 3, ['opaque:mixed:budget']],
            'a promotion one level short of the depth still reaches its callers' => [$promoted, 'C', 't', 2, ['literal:string:x', 'literal:string:y']],
            'a promotion at the depth limit stops at its parameter' => [$promoted, 'C', 't', 3, ['opaque:string:budget']],
            'a property nothing writes' => [$writers, 'C', 'none', 0, []],
            'a class the source does not declare' => [$writers, 'Missing', 't', 0, []],
        ];
    }

    public function testValuesOfSkipsTheWritersOnceTheBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { private $t = "c"; public function __construct(private string $u) { $this->t = "a"; } } new C("x");',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $budget = new EvaluationBudget(0);
        $tree = new SourceTree([$file]);
        $names = new FreeNames();
        $modified = new ModifiedNames($names);
        $writes = new PropertyWrites($index, $modified);
        $expressions = (new Interpreter($index, [], $budget))->evaluatorFor([$file]);
        $deriver = new Deriver(
            $tree,
            new BackwardSlicer($tree, $budget, $names, $modified),
            new SliceExecutor(null, null, $modified, null, $budget),
            $expressions,
            new EntryBinder(new Callers(new CallerIndex([$file]), $index), $expressions, $budget, null, $writes),
            $budget,
            $names,
        );
        $budget->spend();

        $written = $writes->valuesOf('C', 't', 0, $deriver);
        $promoted = $writes->valuesOf('C', 'u', 0, $deriver);

        self::assertSame(['opaque:mixed:budget'], array_map(static fn (Domain $value): string => $value->signature(), $written));
        self::assertSame(['opaque:string:budget'], array_map(static fn (Domain $value): string => $value->signature(), $promoted));
    }

    public function testValuesOfReadsThePropertyAgainOnceTheFirstReadingIsDone(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { private $t = "c"; public function __construct() { $this->t = "a"; } }',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $writes = new PropertyWrites($index);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);

        $first = $writes->valuesOf('C', 't', 0, $deriver);
        $second = $writes->valuesOf('C', 't', 0, $deriver);

        self::assertSame(['literal:string:a', 'literal:string:c'], array_map(static fn (Domain $value): string => $value->signature(), $first));
        self::assertSame(['literal:string:a', 'literal:string:c'], array_map(static fn (Domain $value): string => $value->signature(), $second));
    }

    public function testWritersOfFindsTheMethodsOfTheClassAndItsAncestorsThatWriteTheProperty(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php abstract class P { abstract public function none(); public function init() { $this->t = 1; } }'
            . ' class C extends P {'
            . ' public function append() { $this->t .= "x"; }'
            . ' public function read() { $x = $this->t; }'
            . ' public function alias() { $this->t = &$x; }'
            . ' public function push() { $this->t[] = 1; }'
            . ' public function other() { $this->u = 1; $that->t = 1; } }',
        );
        $writes = new PropertyWrites((new ProgramIndexBuilder())->build([$file]));

        $writers = $writes->writersOf('C', 't');

        self::assertSame(['append', 'alias', 'push', 'init'], array_map(static fn (Stmt\ClassMethod $method): string => $method->name->toString(), $writers));
        self::assertSame($writers, $writes->writersOf('c', 't'));
        self::assertSame(['other'], array_map(static fn (Stmt\ClassMethod $method): string => $method->name->toString(), $writes->writersOf('C', 'u')));
        self::assertSame([], $writes->writersOf('P', 'u'));
        self::assertSame([], $writes->writersOf('Missing', 't'));
    }

    #[DataProvider('providerWrites')]
    public function testWritesSeesOnlyAssignmentsToThePropertyOfThis(string $code, string $property, bool $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);

        self::assertSame($expected, (new PropertyWrites((new ProgramIndexBuilder())->build([$file])))->writes($method, $property));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function providerWrites(): array
    {
        return [
            'an assignment' => ['<?php class C { function m() { $this->t = 1; } }', 't', true],
            'a compound assignment' => ['<?php class C { function m() { $this->t .= "x"; } }', 't', true],
            'an assignment by reference' => ['<?php class C { function m() { $this->t = &$x; } }', 't', true],
            'an element appended' => ['<?php class C { function m() { $this->t[] = 1; } }', 't', true],
            'a nested element' => ['<?php class C { function m() { $this->t["k"]["j"] = 1; } }', 't', true],
            'an assignment inside a branch' => ['<?php class C { function m() { if ($a) { $this->t = 1; } } }', 't', true],
            'an assignment after unrelated ones' => ['<?php class C { function m() { $x = 1; $this->u = 2; $this->t = 3; } }', 't', true],
            'a read' => ['<?php class C { function m() { $x = $this->t; } }', 't', false],
            'a reference taken to the property' => ['<?php class C { function m() { $r = &$this->t; } }', 't', false],
            'another property' => ['<?php class C { function m() { $this->u = 1; } }', 't', false],
            'the property of another object' => ['<?php class C { function m() { $that->t = 1; } }', 't', false],
            'a local variable of the same name' => ['<?php class C { function m() { $t = 1; } }', 't', false],
            'an empty body' => ['<?php class C { function m() { } }', 't', false],
            'an abstract method' => ['<?php abstract class C { abstract function m(); }', 't', false],
        ];
    }

    public function testPromotionsAreTheConstructorsPromotingAParameterOfThatName(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class P { public function __construct(protected $t, $plain) {} }'
            . ' class M extends P { }'
            . ' class C extends M { public function __construct(private string $t, $u, public readonly int $v) { parent::__construct($t, $u); } }',
        );
        $parent = $file->statements[0];
        $child = $file->statements[2];
        self::assertInstanceOf(Stmt\Class_::class, $parent);
        self::assertInstanceOf(Stmt\Class_::class, $child);
        $writes = new PropertyWrites((new ProgramIndexBuilder())->build([$file]));

        self::assertSame([$child->getMethod('__construct'), $parent->getMethod('__construct')], $writes->promotions('C', 't'));
        self::assertSame([$parent->getMethod('__construct')], $writes->promotions('M', 't'));
        self::assertSame([$child->getMethod('__construct')], $writes->promotions('C', 'v'));
        self::assertSame([], $writes->promotions('C', 'u'));
        self::assertSame([], $writes->promotions('P', 'plain'));
        self::assertSame([], $writes->promotions('Missing', 't'));
    }

    public function testDefaultOfIsTheNearestDeclaredDefault(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class P { public $t = "p"; public $u = "pu"; } class C extends P { public $t = "c"; public $n; public $z = null; }',
        );
        $writes = new PropertyWrites((new ProgramIndexBuilder())->build([$file]));

        $own = $writes->defaultOf('C', 't');
        $inherited = $writes->defaultOf('C', 'u');

        self::assertInstanceOf(Scalar\String_::class, $own);
        self::assertSame('c', $own->value);
        self::assertInstanceOf(Scalar\String_::class, $inherited);
        self::assertSame('pu', $inherited->value);
        self::assertInstanceOf(Expr\ConstFetch::class, $writes->defaultOf('C', 'z'));
        self::assertNull($writes->defaultOf('C', 'n'));
        self::assertNull($writes->defaultOf('C', 'missing'));
        self::assertNull($writes->defaultOf('Missing', 't'));
    }
}
