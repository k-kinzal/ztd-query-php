<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
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
use SqlCatalog\Analysis\Derivation\CallerSet;
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
use SqlCatalog\Evaluation\Term;
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

#[CoversClass(EntryBinder::class)]
#[UsesClass(CallEvaluator::class)]
#[UsesClass(ConstantReader::class)]
#[UsesClass(Binding::class)]
#[UsesClass(CalleeReturns::class)]
#[UsesClass(CallerIndex::class)]
#[UsesClass(Callers::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(FreeNames::class)]
#[UsesClass(ModifiedNames::class)]
#[UsesClass(PropertyWrites::class)]
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
#[UsesClass(FunctionShape::class)]
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
#[UsesClass(CallerSet::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class EntryBinderTest extends TestCase
{
    /**
     * @param list<list<string|int|float|bool|null>> $values
     * @param list<list<string>> $through
     * @param list<bool> $truncated
     * @param list<bool> $combined
     */
    #[DataProvider('providerAffordable')]
    public function testAffordableJoinsTheWaysInBeyondTheLimitIntoOne(int $limit, array $values, array $through, array $truncated, array $combined): void
    {
        $bindings = [
            new Binding(new Environment(['a' => Domain::literal(1)]), ['w1']),
            new Binding(new Environment(['a' => Domain::literal(2)]), ['w2']),
            new Binding(new Environment(['a' => Domain::literal(3)]), ['w3'], true),
            new Binding(new Environment(['a' => Domain::literal(4)]), ['w4']),
        ];

        $affordable = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder()->affordable($bindings, $limit);

        self::assertSame($values, array_map(
            static fn (Binding $binding): array => array_map(
                static fn (Term $term): string|int|float|bool|null => $term instanceof LiteralTerm ? $term->value : null,
                $binding->environment->read('a')->terms,
            ),
            $affordable,
        ));
        self::assertSame($through, array_map(static fn (Binding $binding): array => $binding->through, $affordable));
        self::assertSame($truncated, array_map(static fn (Binding $binding): bool => $binding->truncated, $affordable));
        self::assertSame($combined, array_map(static fn (Binding $binding): bool => $binding->combined, $affordable));
    }

    /**
     * @return array<string, array{int, list<list<int>>, list<list<string>>, list<bool>, list<bool>}>
     */
    public static function providerAffordable(): array
    {
        return [
            'more room than ways in' => [5, [[1], [2], [3], [4]], [['w1'], ['w2'], ['w3'], ['w4']], [false, false, true, false], [false, false, false, false]],
            'exactly as many ways in as there is room for' => [4, [[1], [2], [3], [4]], [['w1'], ['w2'], ['w3'], ['w4']], [false, false, true, false], [false, false, false, false]],
            'one too many' => [3, [[1], [2], [3, 4]], [['w1'], ['w2'], ['w4']], [false, false, true], [false, false, true]],
            'the rest joined, cut short when any of them was' => [2, [[1], [2, 3, 4]], [['w1'], ['w4']], [false, true], [false, true]],
            'room for one' => [1, [[1, 2, 3, 4]], [['w4']], [true], [true]],
            'no room at all' => [0, [[1, 2, 3, 4]], [['w4']], [true], [true]],
        ];
    }

    public function testAffordableKeepsTheSameWaysInWithinTheLimit(): void
    {
        $bindings = [new Binding(new Environment(), ['f']), new Binding(new Environment(), ['g'])];
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        self::assertSame($bindings, $binder->affordable($bindings, 2));
    }

    public function testAffordableJoinsTheRestIntoOneThatIsNotCutShortWhenNoneOfThemWas(): void
    {
        $bindings = [
            new Binding(new Environment(['a' => Domain::literal(1)]), ['w1'], true),
            new Binding(new Environment(['a' => Domain::literal(2)]), ['w2']),
            new Binding(new Environment(['a' => Domain::literal(3)]), ['w3']),
        ];

        $affordable = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder()->affordable($bindings, 2);

        self::assertCount(2, $affordable);
        self::assertSame($bindings[0], $affordable[0]);
        self::assertFalse($affordable[1]->truncated);
        self::assertTrue($affordable[1]->combined);
    }

    public function testAffordableJoinsNothingIntoAnEmptyWayIn(): void
    {
        $affordable = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder()->affordable([], -1);

        self::assertCount(1, $affordable);
        self::assertSame([], $affordable[0]->environment->names());
        self::assertSame([], $affordable[0]->through);
        self::assertFalse($affordable[0]->truncated);
        self::assertTrue($affordable[0]->combined);
    }

    /**
     * @param list<string> $needs
     * @param list<array{string, list<string>, bool, bool}> $expected
     */
    #[DataProvider('providerBindings')]
    public function testBindingsBindEachPropertyOfThisToEveryValueTheClassLeavesIt(array $needs, array $expected): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { private $p = "p0"; private $q = "q0"; private $r = "r0";'
            . ' public function __construct() { $this->p = "p1"; $this->q = "q1"; }'
            . ' public function m($x) { } }',
        );
        $method = (new NodeFinder())->findFirst(
            $file->statements,
            static fn (Node $node): bool => $node instanceof Stmt\ClassMethod && $node->name->toString() === 'm',
        );
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->bindings(new Arrival($method, Pending::needing(array_fill_keys($needs, true))), 0, $deriver);

        self::assertSame($expected, array_map(
            static fn (Binding $binding): array => [$binding->environment->signature(), $binding->through, $binding->truncated, $binding->combined],
            $bindings,
        ));
    }

    /**
     * @return array<string, array{list<string>, list<array{string, list<string>, bool, bool}>}>
     */
    public static function providerBindings(): array
    {
        return [
            'one property with two values' => [
                ['this->p'],
                [
                    ['this->p=literal:string:p1', ['C::m'], false, false],
                    ['this->p=literal:string:p0', ['C::m'], false, false],
                ],
            ],
            'two properties with two values each are paired without knowing they go together' => [
                ['this->p', 'this->q'],
                [
                    ['this->p=literal:string:p1;this->q=literal:string:q1', ['C::m'], false, true],
                    ['this->p=literal:string:p1;this->q=literal:string:q0', ['C::m'], false, true],
                    ['this->p=literal:string:p0;this->q=literal:string:q1', ['C::m'], false, true],
                    ['this->p=literal:string:p0;this->q=literal:string:q0', ['C::m'], false, true],
                ],
            ],
            'a property with one value before one with two' => [
                ['this->r', 'this->p', 'x'],
                [
                    ['this->p=literal:string:p1;this->r=literal:string:r0;x=opaque:mixed:parameter', ['C::m'], false, false],
                    ['this->p=literal:string:p0;this->r=literal:string:r0;x=opaque:mixed:parameter', ['C::m'], false, false],
                ],
            ],
            'a property with two values before one with one' => [
                ['this->p', 'this->r'],
                [
                    ['this->p=literal:string:p1;this->r=literal:string:r0', ['C::m'], false, false],
                    ['this->p=literal:string:p0;this->r=literal:string:r0', ['C::m'], false, false],
                ],
            ],
            'a property the class never gives a value' => [
                ['this->none'],
                [
                    ['', ['C::m'], false, false],
                ],
            ],
            'no property at all' => [
                ['this', 'y'],
                [
                    ['y=opaque:null:unresolved', ['C::m'], false, false],
                ],
            ],
        ];
    }

    public function testBindingsLeaveThePropertiesAloneWhereThereIsNoObject(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { private $p = "p0"; public static function s() { } } function f() { }',
        );
        $static = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        $function = $file->statements[1];
        self::assertInstanceOf(Stmt\ClassMethod::class, $static);
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();

        $ofStatic = $binder->bindings(new Arrival($static, Pending::needing(['this->p' => true])), 0, $deriver);
        $ofFunction = $binder->bindings(new Arrival($function, Pending::needing(['this->p' => true])), 0, $deriver);
        $ofFile = $binder->bindings(new Arrival(null, Pending::needing(['this->p' => true])), 0, $deriver);

        self::assertCount(1, $ofStatic);
        self::assertSame('', $ofStatic[0]->environment->signature());
        self::assertSame(['C::s'], $ofStatic[0]->through);
        self::assertCount(1, $ofFunction);
        self::assertSame('', $ofFunction[0]->environment->signature());
        self::assertSame(['f'], $ofFunction[0]->through);
        self::assertCount(1, $ofFile);
        self::assertSame('this->p=opaque:mixed:unresolved', $ofFile[0]->environment->signature());
        self::assertSame(['{main}'], $ofFile[0]->through);
    }

    public function testBindingsLeaveThePropertiesOpenOnAPathTheBudgetStopped(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { private $p = "p0"; public function m() { } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->bindings(new Arrival($method, Pending::needing(['this->p' => true])->exhaust()), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('this->p=opaque:mixed:budget', $bindings[0]->environment->signature());
        self::assertSame(['C::m'], $bindings[0]->through);
    }

    public function testBindingsLeaveThePropertiesAloneWithoutAReaderOfPropertyWrites(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { private $p = "p0"; public function m() { } }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, []))->deriverFor([$file]);
        $binder = new EntryBinder(new Callers(new CallerIndex([$file]), $index), $deriver->evaluator(), new EvaluationBudget());

        $bindings = $binder->bindings(new Arrival($method, Pending::needing(['this->p' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('', $bindings[0]->environment->signature());
        self::assertSame(['C::m'], $bindings[0]->through);
    }

    public function testWithPropertySplitsEveryWayInByEachValue(): void
    {
        $first = new Binding(new Environment(['k' => Domain::literal(1)]), ['a'], true);
        $second = new Binding(new Environment(), ['b'], false, true);
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        $split = $binder->withProperty([$first, $second], 'this->p', [Domain::literal('x'), Domain::literal('y')]);

        self::assertSame(
            [
                ['k=literal:int:1;this->p=literal:string:x', ['a'], true, false],
                ['k=literal:int:1;this->p=literal:string:y', ['a'], true, false],
                ['this->p=literal:string:x', ['b'], false, true],
                ['this->p=literal:string:y', ['b'], false, true],
            ],
            array_map(
                static fn (Binding $binding): array => [$binding->environment->signature(), $binding->through, $binding->truncated, $binding->combined],
                $split,
            ),
        );
        self::assertSame('k=literal:int:1', $first->environment->signature());
        self::assertSame('', $second->environment->signature());
    }

    public function testWithPropertyMarksEveryWayInCombinedWhenAsked(): void
    {
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        $split = $binder->withProperty([new Binding(new Environment(), ['a'])], 'this->p', [Domain::literal('x'), Domain::literal('y')], true);

        self::assertSame([true, true], array_map(static fn (Binding $binding): bool => $binding->combined, $split));
        self::assertSame([false, false], array_map(static fn (Binding $binding): bool => $binding->truncated, $split));
    }

    public function testWithPropertyLeavesTheWaysInAloneWithoutValues(): void
    {
        $bindings = [new Binding(new Environment(), ['a'])];
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        self::assertSame($bindings, $binder->withProperty($bindings, 'this->p', [], true));
    }

    #[DataProvider('providerWithPropertyLimit')]
    public function testWithPropertyKeepsNoMoreWaysInThanTheSolutionLimit(int $valueCount, int $count, bool $truncated, int $last): void
    {
        $bindings = [
            new Binding(new Environment(), ['w1'], false, true),
            new Binding(new Environment(), ['w2']),
            new Binding(new Environment(), ['w3']),
            new Binding(new Environment(), ['w4']),
        ];
        $values = array_map(static fn (int $value): Domain => Domain::literal($value), range(1, $valueCount));
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        $split = $binder->withProperty($bindings, 'this->p', $values);

        self::assertCount($count, $split);
        self::assertSame(array_fill(0, $count, $truncated), array_map(static fn (Binding $binding): bool => $binding->truncated, $split));
        self::assertSame(
            array_merge(array_fill(0, $valueCount, true), array_fill(0, $count - $valueCount, false)),
            array_map(static fn (Binding $binding): bool => $binding->combined, $split),
        );
        self::assertSame(['w4'], $split[Deriver::MAX_SOLUTIONS - 1]->through);
        self::assertSame($last, $split[Deriver::MAX_SOLUTIONS - 1]->environment->read('this->p')->soleLiteral()?->value);
    }

    /**
     * @return array<string, array{int, int, bool, int}>
     */
    public static function providerWithPropertyLimit(): array
    {
        return [
            'exactly the limit' => [8, 32, false, 8],
            'one row over the limit' => [9, 32, true, 5],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerParameterValues')]
    public function testParameterValuesReadWhatEveryCallerPasses(string $name, int $depth, array $expected): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } f(1); f("x");');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $values = $deriver->binder()->parameterValues($function, $name, $depth, $deriver);

        self::assertSame($expected, array_map(static fn (Domain $value): string => $value->signature(), $values));
    }

    /**
     * @return array<string, array{string, int, list<string>}>
     */
    public static function providerParameterValues(): array
    {
        return [
            'a parameter every caller passes' => ['a', 0, ['literal:int:1', 'literal:string:x']],
            'a parameter at the depth limit' => ['a', 4, ['opaque:mixed:budget']],
            'a name that is not a parameter' => ['b', 0, ['opaque:null:unresolved']],
        ];
    }

    public function testEvaluateConstantReadsConstantsOfTheClassGiven(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { const X = "k"; }');
        $binder = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file])->binder();
        $expression = new Expr\BinaryOp\Concat(new Expr\ClassConstFetch(new Node\Name('self'), 'X'), new Scalar\String_('_t'));

        self::assertSame('literal:string:k_t', $binder->evaluateConstant($expression, 'C')->signature());
        self::assertSame("pattern:hole:unresolved\x1ftext:_t", $binder->evaluateConstant($expression, null)->signature());
        self::assertSame('opaque:mixed:unresolved', $binder->evaluateConstant(new Expr\Variable('v'), 'C')->signature());
    }

    public function testParameterBindingsLeaveEverythingOpenOnAPathTheBudgetStopped(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } f(1);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(
            new Arrival($function, Pending::needing(['a' => true, 'this->p' => true])->exhaust()),
            0,
            $deriver,
        );

        self::assertCount(1, $bindings);
        self::assertSame('a=opaque:mixed:budget;this->p=opaque:mixed:budget', $bindings[0]->environment->signature());
        self::assertSame(['f'], $bindings[0]->through);
        self::assertFalse($bindings[0]->truncated);
        self::assertFalse($bindings[0]->combined);
    }

    public function testParameterBindingsReadWhatTheTopOfAFileIsDeclaredToHold(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $wpdb->query($db);');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $deriver = (new Interpreter($index, [], null, new DeclaredGlobals(['wpdb' => 'wpdb'])))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival(null, Pending::needing(['wpdb' => true, 'db' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('db=opaque:mixed:unresolved;wpdb=object:wpdb::', $bindings[0]->environment->signature());
        self::assertSame(['{main}'], $bindings[0]->through);
    }

    public function testParameterBindingsLeaveUndefinedLocalsNullTypedWithoutAskingCallers(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } f(1);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(
            new Arrival($function, Pending::needing(['x' => true, 'this' => true, 'this->p' => true, 'thisx' => true])),
            0,
            $deriver,
        );

        self::assertCount(1, $bindings);
        self::assertSame(['x', 'thisx'], $bindings[0]->environment->names());
        self::assertSame('thisx=opaque:null:unresolved;x=opaque:null:unresolved', $bindings[0]->environment->signature());
        self::assertSame(['f'], $bindings[0]->through);
    }

    public function testParameterBindingsFollowEveryCallerOfTheBody(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f(int $a, $b = "d", $c = 3) { } function g() { f(1, c: 5); } f(2, "e");',
        );
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(
            new Arrival($function, Pending::needing(['a' => true, 'b' => true, 'c' => true, 'x' => true])),
            0,
            $deriver,
        );

        self::assertSame(
            [
                ['a=literal:int:1;b=literal:string:d;c=literal:int:5;x=opaque:null:unresolved', ['g', 'f'], false, false],
                ['a=literal:int:2;b=literal:string:e;c=literal:int:3;x=opaque:null:unresolved', ['{main}', 'f'], false, false],
            ],
            array_map(
                static fn (Binding $binding): array => [$binding->environment->signature(), $binding->through, $binding->truncated, $binding->combined],
                $bindings,
            ),
        );
    }

    /**
     * @param list<string> $through
     */
    #[DataProvider('providerParameterBindingsDepth')]
    public function testParameterBindingsStopAtTheDepthLimit(int $depth, int $maxDepth, string $expected, array $through): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(int $a) { } f(1);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), [], new EvaluationBudget(20000, $maxDepth)))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($function, Pending::needing(['a' => true])), $depth, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame($expected, $bindings[0]->environment->signature());
        self::assertSame($through, $bindings[0]->through);
    }

    /**
     * @return array<string, array{int, int, string, list<string>}>
     */
    public static function providerParameterBindingsDepth(): array
    {
        return [
            'one short of the default limit' => [3, 4, 'a=literal:int:1', ['{main}', 'f']],
            'at the default limit' => [4, 4, 'a=opaque:int:budget', ['f']],
            'one short of a lower limit' => [1, 2, 'a=literal:int:1', ['{main}', 'f']],
            'at a lower limit' => [2, 2, 'a=opaque:int:budget', ['f']],
        ];
    }

    public function testParameterBindingsLeaveAParameterNothingCallsOpen(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(int $a) { }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($function, Pending::needing(['a' => true, 'x' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('a=opaque:int:parameter;x=opaque:null:unresolved', $bindings[0]->environment->signature());
        self::assertSame(['f'], $bindings[0]->through);
        self::assertFalse($bindings[0]->truncated);
    }

    public function testParameterBindingsLeaveAParameterOpenOnceTheBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(int $a) { }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $budget = new EvaluationBudget(0);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), [], $budget))->deriverFor([$file]);
        $budget->spend();

        $bindings = $deriver->binder()->parameterBindings(new Arrival($function, Pending::needing(['a' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('a=opaque:int:budget', $bindings[0]->environment->signature());
        self::assertSame(['f'], $bindings[0]->through);
    }

    public function testParameterBindingsBlameTheBudgetWhenLookingForCallersSpentIt(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { function find($id) { } } function g($r) { $r->find(1); }');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $budget = new EvaluationBudget(0);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), [], $budget))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($method, Pending::needing(['id' => true])), 0, $deriver);

        self::assertTrue($budget->isExhausted());
        self::assertCount(1, $bindings);
        self::assertSame('id=opaque:mixed:budget', $bindings[0]->environment->signature());
        self::assertSame(['R::find'], $bindings[0]->through);
        self::assertTrue($bindings[0]->truncated);
    }

    public function testParameterBindingsLeaveTheParameterOpenAndCutShortWhenNoCallCanBeConfirmed(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class R { function find($id) { } } class S { function find($id) { } } function g($r) { $r->find(1); }',
        );
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($method, Pending::needing(['id' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('id=opaque:mixed:parameter', $bindings[0]->environment->signature());
        self::assertTrue($bindings[0]->truncated);
    }

    public function testParameterBindingsLeaveTheParameterOpenWithoutCuttingShortWhenNothingCallsIt(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class R { function find($id) { } } class S { function find($id) { } } (new S())->find(1);');
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($method, Pending::needing(['id' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('id=opaque:mixed:parameter', $bindings[0]->environment->signature());
        self::assertFalse($bindings[0]->truncated);
    }

    public function testFromCallersBindsEachArgumentAndFillsInWhatACallLeavesOut(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a, $b = "d", $c = 3, int $e) { } f(1, c: 5);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();
        $outside = new Environment(['o' => Domain::literal('out')]);
        $calls = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class));

        $bindings = $binder->fromCallers(
            new CallerSet($calls),
            $binder->parametersOf($function, ['a' => true, 'b' => true, 'c' => true, 'e' => true]),
            $outside,
            'f',
            0,
            $deriver,
        );

        self::assertSame(
            [['a=literal:int:1;b=literal:string:d;c=literal:int:5;e=opaque:int:parameter;o=literal:string:out', ['{main}', 'f'], false, false]],
            array_map(
                static fn (Binding $binding): array => [$binding->environment->signature(), $binding->through, $binding->truncated, $binding->combined],
                $bindings,
            ),
        );
        self::assertSame('o=literal:string:out', $outside->signature());
    }

    /**
     * @param list<int> $values
     */
    #[DataProvider('providerFromCallersLimit')]
    public function testFromCallersAsksNoMoreThanTheCallerLimit(int $callCount, array $values, bool $truncated): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($a) { } ' . implode(' ', array_map(static fn (int $value): string => 'f(' . $value . ');', range(1, 13))),
        );
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();
        $calls = array_slice(array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class)), 0, $callCount);

        $bindings = $binder->fromCallers(new CallerSet($calls), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver);

        self::assertSame($values, array_map(static fn (Binding $binding): mixed => $binding->environment->read('a')->soleLiteral()?->value, $bindings));
        self::assertSame(array_fill(0, count($values), $truncated), array_map(static fn (Binding $binding): bool => $binding->truncated, $bindings));
    }

    /**
     * @return array<string, array{int, list<int>, bool}>
     */
    public static function providerFromCallersLimit(): array
    {
        return [
            'exactly the limit' => [12, range(1, 12), false],
            'one caller over the limit' => [13, range(1, 12), true],
        ];
    }

    public function testFromCallersCarriesWhetherTheCallerWasCutShort(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($a) { } function g($x) { f($x); } ' . implode(' ', array_map(static fn (int $value): string => 'g(' . $value . ');', range(1, 13))),
        );
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();

        $bindings = $binder->fromCallers(new CallerSet([$call]), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver);

        self::assertSame(range(1, 12), array_map(static fn (Binding $binding): mixed => $binding->environment->read('a')->soleLiteral()?->value, $bindings));
        self::assertSame(array_fill(0, 12, true), array_map(static fn (Binding $binding): bool => $binding->truncated, $bindings));
        self::assertSame(array_fill(0, 12, false), array_map(static fn (Binding $binding): bool => $binding->combined, $bindings));
        self::assertSame(['{main}', 'g', 'f'], $bindings[0]->through);
    }

    public function testFromCallersCarriesWhetherTheCallerPairedValuesWorkedOutApart(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php function f($a) { } class C { private $p = "p0"; private $q = "q0";'
            . ' public function __construct() { $this->p = "p1"; $this->q = "q1"; }'
            . ' public function run() { f($this->p . $this->q); } }',
        );
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\FuncCall::class);
        self::assertInstanceOf(Expr\FuncCall::class, $call);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();

        $bindings = $binder->fromCallers(new CallerSet([$call]), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver);

        self::assertSame(
            ['p1q1', 'p1q0', 'p0q1', 'p0q0'],
            array_map(static fn (Binding $binding): mixed => $binding->environment->read('a')->soleLiteral()?->value, $bindings),
        );
        self::assertSame([true, true, true, true], array_map(static fn (Binding $binding): bool => $binding->combined, $bindings));
        self::assertSame([false, false, false, false], array_map(static fn (Binding $binding): bool => $binding->truncated, $bindings));
        self::assertSame(['C::run', 'f'], $bindings[0]->through);
    }

    public function testFromCallersMarksEveryWayInCutShortWhenTheCallersArePartial(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } f(1); f(2);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);
        $binder = $deriver->binder();
        $calls = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class));

        $bindings = $binder->fromCallers(new CallerSet($calls, true), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver);

        self::assertSame([1, 2], array_map(static fn (Binding $binding): mixed => $binding->environment->read('a')->soleLiteral()?->value, $bindings));
        self::assertSame([true, true], array_map(static fn (Binding $binding): bool => $binding->truncated, $bindings));
    }

    public function testFromCallersMarksTheWaysInFoundBeforeTheBudgetRanOutAsCutShort(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } $x = 1; $y = $x; $z = $y; f($z); f(2);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), [], new EvaluationBudget(8)))->deriverFor([$file]);
        $binder = $deriver->binder();
        $calls = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class));

        $bindings = $binder->fromCallers(new CallerSet($calls), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver);

        self::assertSame([1], array_map(static fn (Binding $binding): mixed => $binding->environment->read('a')->soleLiteral()?->value, $bindings));
        self::assertSame([true], array_map(static fn (Binding $binding): bool => $binding->truncated, $bindings));
    }

    public function testFromCallersStopsOnceTheBudgetIsSpent(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a) { } f(1);');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $budget = new EvaluationBudget(0);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), [], $budget))->deriverFor([$file]);
        $binder = $deriver->binder();
        $calls = array_values((new NodeFinder())->findInstanceOf($file->statements, Expr\FuncCall::class));
        $budget->spend();

        self::assertSame([], $binder->fromCallers(new CallerSet($calls), $binder->parametersOf($function, ['a' => true]), new Environment(), 'f', 0, $deriver));
    }

    /**
     * @param list<string> $needs
     * @param array<string, string|null> $expected
     */
    #[DataProvider('providerArgumentsFor')]
    public function testArgumentsForFindsTheArgumentPassedForEachParameter(string $code, array $needs, array $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $call = (new NodeFinder())->findFirstInstanceOf($file->statements, Expr\CallLike::class);
        self::assertInstanceOf(Expr\CallLike::class, $call);
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([$file])->binder();

        $arguments = $binder->argumentsFor($call, $binder->parametersOf($function, array_fill_keys($needs, true)));

        self::assertSame($expected, array_map(
            static fn (?Expr $argument): ?string => $argument === null ? null : (new Standard())->prettyPrintExpr($argument),
            $arguments,
        ));
    }

    /**
     * @return array<string, array{string, list<string>, array<string, string|null>}>
     */
    public static function providerArgumentsFor(): array
    {
        return [
            'by position' => ['<?php function f($a, $b) { } f(1, 2);', ['a', 'b'], ['a' => '1', 'b' => '2']],
            'by name, in another order' => ['<?php function f($a, $b) { } f(b: 2, a: 1);', ['a', 'b'], ['a' => '1', 'b' => '2']],
            'by name, at the position of another parameter' => ['<?php function f($a, $b) { } f(b: 2);', ['a', 'b'], ['a' => null, 'b' => '2']],
            'by position, then by name' => ['<?php function f($a, $b, $c) { } f(1, c: 3);', ['a', 'b', 'c'], ['a' => '1', 'b' => null, 'c' => '3']],
            'left out' => ['<?php function f($a, $b) { } f(1);', ['a', 'b'], ['a' => '1', 'b' => null]],
            'only what is needed' => ['<?php function f($a, $b) { } f(1, 2);', ['b'], ['b' => '2']],
            'unpacked' => ['<?php function f($a, $b) { } f(...$xs);', ['a', 'b'], ['a' => null, 'b' => null]],
            'into a variadic parameter' => ['<?php function f($a, ...$rest) { } f(1, 2, 3);', ['a', 'rest'], ['a' => '1', 'rest' => null]],
            'a first class callable' => ['<?php function f($a) { } f(...);', ['a'], ['a' => null]],
            'to a constructor' => ['<?php function f($a) { } new C(7);', ['a'], ['a' => '7']],
            'to a method' => ['<?php function f($a, $b) { } $o->m(1, 2);', ['b'], ['b' => '2']],
        ];
    }

    #[DataProvider('providerDefaultOf')]
    public function testDefaultOfIsWhatAParameterHoldsWhenACallLeavesItOut(Node\Param $parameter, string $signature, ?string $expression): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { const X = "k"; }');
        $binder = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file])->binder();

        $default = $binder->defaultOf($parameter);

        self::assertSame($signature, $default->signature());
        self::assertSame($expression, $default->terms[0] instanceof OpaqueTerm ? $default->terms[0]->expression : null);
    }

    /**
     * @return array<string, array{Node\Param, string, string|null}>
     */
    public static function providerDefaultOf(): array
    {
        return [
            'a declared default' => [new Node\Param(new Expr\Variable('b'), new Scalar\String_('d')), 'literal:string:d', null],
            'a default read from a class constant' => [new Node\Param(new Expr\Variable('b'), new Expr\ClassConstFetch(new Node\Name('C'), 'X')), 'literal:string:k', null],
            'a null default' => [new Node\Param(new Expr\Variable('b'), new Expr\ConstFetch(new Node\Name('null')), new Node\NullableType(new Node\Identifier('string'))), 'literal:null:', null],
            'no default' => [new Node\Param(new Expr\Variable('a'), null, new Node\Identifier('int')), 'opaque:int:parameter', '$a'],
            'variadic' => [new Node\Param(new Expr\Variable('xs'), null, new Node\Identifier('string'), false, true), 'opaque:string:parameter', '...$xs'],
            'variadic with a default written anyway' => [new Node\Param(new Expr\Variable('xs'), new Scalar\String_('d'), null, false, true), 'opaque:mixed:parameter', '...$xs'],
            'a name that is not written plainly' => [new Node\Param(new Expr\Variable(new Expr\Variable('n'))), 'opaque:mixed:parameter', '$'],
            'no variable at all' => [new Node\Param(new Expr\Error(), null, new Node\Identifier('int')), 'opaque:int:parameter', '$'],
        ];
    }

    public function testOpenOriginBlamesTheBudgetOnlyOnceItIsSpent(): void
    {
        $budget = new EvaluationBudget(1);
        $binder = (new Interpreter(new ProgramIndex(), [], $budget))->deriverFor([])->binder();

        $fresh = $binder->openOrigin();
        $budget->spend();
        $atTheLimit = $binder->openOrigin();
        $budget->spend();
        $spent = $binder->openOrigin();

        self::assertSame(Origin::Parameter, $fresh);
        self::assertSame(Origin::Parameter, $atTheLimit);
        self::assertSame(Origin::Budget, $spent);
    }

    public function testParametersOfAreTheNeededParametersWithTheirPositions(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f($a, $b, $c) { }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([$file])->binder();

        self::assertSame(
            ['a' => [0, $function->params[0]], 'c' => [2, $function->params[2]]],
            $binder->parametersOf($function, ['c' => true, 'a' => true, 'z' => true]),
        );
        self::assertSame([], $binder->parametersOf($function, []));
    }

    public function testParametersOfSkipsParametersWithoutAPlainName(): void
    {
        $unnamed = new Node\Param(new Expr\Error());
        $computed = new Node\Param(new Expr\Variable(new Expr\Variable('n')));
        $named = new Node\Param(new Expr\Variable('a'));
        $closure = new Expr\Closure(['params' => [$unnamed, $computed, $named]]);
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        self::assertSame(['a' => [2, $named]], $binder->parametersOf($closure, ['a' => true, 'n' => true, '' => true]));
    }

    public function testWithParametersLeavesEveryParameterOpenForTheReasonGiven(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php function f(int $a, $b) { }');
        $function = $file->statements[0];
        self::assertInstanceOf(Stmt\Function_::class, $function);
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([$file])->binder();
        $environment = new Environment(['x' => Domain::literal(1)]);

        $bound = $binder->withParameters($environment, $binder->parametersOf($function, ['a' => true, 'b' => true]), Origin::Budget);

        self::assertSame('a=opaque:int:budget;b=opaque:mixed:budget;x=literal:int:1', $bound->signature());
        self::assertSame(
            ['$a', '$b'],
            array_map(static fn (string $name): ?string => $bound->read($name)->terms[0] instanceof OpaqueTerm ? $bound->read($name)->terms[0]->expression : null, ['a', 'b']),
        );
        self::assertSame('x=literal:int:1', $environment->signature());
    }

    public function testLeaveOpenLeavesEveryNeededNameOpenForTheReasonGiven(): void
    {
        $binder = (new Interpreter(new ProgramIndex(), []))->deriverFor([])->binder();

        $environment = $binder->leaveOpen(['a' => true, 'this->p' => true], Origin::Parameter);

        self::assertSame(['a', 'this->p'], $environment->names());
        self::assertSame('a=opaque:mixed:parameter;this->p=opaque:mixed:parameter', $environment->signature());
        self::assertSame(
            ['$a', '$this->p'],
            array_map(static fn (string $name): ?string => $environment->read($name)->terms[0] instanceof OpaqueTerm ? $environment->read($name)->terms[0]->expression : null, ['a', 'this->p']),
        );
        self::assertSame([], $binder->leaveOpen([], Origin::Budget)->names());
    }

    public function testFileScopeBindsTheGlobalsAnExtensionDeclares(): void
    {
        $index = new ProgramIndex();
        $declared = new EntryBinder(
            new Callers(new CallerIndex(), $index),
            (new Interpreter($index, []))->evaluatorFor(),
            new EvaluationBudget(),
            new DeclaredGlobals(['wpdb' => 'wpdb']),
        );
        $undeclared = new EntryBinder(new Callers(new CallerIndex(), $index), (new Interpreter($index, []))->evaluatorFor(), new EvaluationBudget());

        $environment = $declared->fileScope(['wpdb' => true, 'db' => true]);

        self::assertSame(['wpdb', 'db'], $environment->names());
        self::assertSame('db=opaque:mixed:unresolved;wpdb=object:wpdb::', $environment->signature());
        self::assertSame('wpdb=opaque:mixed:unresolved', $undeclared->fileScope(['wpdb' => true])->signature());
        self::assertSame(
            '$db',
            $environment->read('db')->terms[0] instanceof OpaqueTerm ? $environment->read('db')->terms[0]->expression : null,
        );
    }

    public function testParameterBindingsBindMethodParametersFromCallsOnInstancesOfTheClass(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class R { public function find($id) { } } function g(R $r) { $r->find(5); }',
        );
        $method = (new NodeFinder())->findFirstInstanceOf($file->statements, Stmt\ClassMethod::class);
        self::assertInstanceOf(Stmt\ClassMethod::class, $method);
        $deriver = (new Interpreter((new ProgramIndexBuilder())->build([$file]), []))->deriverFor([$file]);

        $bindings = $deriver->binder()->parameterBindings(new Arrival($method, Pending::needing(['id' => true])), 0, $deriver);

        self::assertCount(1, $bindings);
        self::assertSame('id=literal:int:5', $bindings[0]->environment->signature());
        self::assertSame(['g', 'R::find'], $bindings[0]->through);
    }
}
