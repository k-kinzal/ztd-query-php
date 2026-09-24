<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Analysis\ExternalInput;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Analysis\ReferenceEvaluator;
use SqlCatalog\Analysis\StatementRecorder;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ReferenceEvaluator::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(StatementRecorder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(Environment::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(ExternalInput::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Php\MethodShape::class)]
#[UsesClass(\SqlCatalog\Php\ParameterShape::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\TypeReader::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Extension\Model\CallContext::class)]
final class ReferenceEvaluatorTest extends TestCase
{
    #[DataProvider('providerEvaluate')]
    public function testEvaluate(string $code, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor();
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
            ['<?php $result = "a";', 'a'],
            ['<?php $result = $_GET["x"];', '{$}'],
            ['<?php const T = "users"; $result = T;', 'users'],
            ['<?php class C { const T = "users"; } $result = C::T;', 'users'],
            ['<?php class C {} $result = C::class;', 'C'],
            ['<?php $result = ["a", "b"][1];', 'b'],
            ['<?php $result = ["k" => "v"]["k"];', 'v'],
            ['<?php $result = new \\PDO("sqlite::memory:");', '{$}'],
            ['<?php $result = static function (): void {};', '{$}'],
            ['<?php $result = UNDEFINED_CONSTANT;', '{$}'],
            ['<?php class C { public string $t = "users"; } $c = new C(); $result = $c->t;', 'users'],
        ];
    }

    public function testReadArrayIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ["a", "b"];');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(Expr\Array_::class, $node);

        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $read = $evaluator->readArray($node, new Environment(), new FunctionScope('t.php'), $expressions);

        $array = $read->soleArray();
        self::assertNotNull($array);
        self::assertCount(2, $array->entries);
    }

    public function testReadElementIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ["a", "b"][1];');
        $statement = $file->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(ArrayDimFetch::class, $node);

        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $read = $evaluator->readElement($node, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame('b', $read->soleLiteral()?->value);
    }

    public function testReadPropertyIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { public string $t = "users"; } $c = new C(); $c->t;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $statement = $file->statements[2];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(PropertyFetch::class, $node);

        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $environment = new Environment(['c' => Domain::of(new \SqlCatalog\Evaluation\ObjectTerm('C'))]);
        $read = $evaluator->readProperty($node, $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame('users', $read->soleLiteral()?->value);
    }

    public function testEvaluateOnlyAnswersForReferences(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $answer = $evaluator->evaluate(new String_('a'), new Environment(), new FunctionScope('t.php'), $expressions);
        self::assertNull($answer);
    }

    public function testReadVariableResolvesThisToTheEnclosingClass(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $inClass = $evaluator->readVariable(new Variable('this'), new Environment(), new FunctionScope('t.php', 'C::m', 'C'));
        $outside = $evaluator->readVariable(new Variable('this'), new Environment(), new FunctionScope('t.php'));
        self::assertSame('C', $inClass->soleObject()?->className);
        self::assertNull($outside->soleObject());
    }

    public function testReadVariableGivesUpOnAVariableVariable(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $read = $evaluator->readVariable(new Variable(new Variable('name')), new Environment(), new FunctionScope('t.php'));
        self::assertSame('mixed', $read->type()->display());
    }

    public function testReadArrayMarksAnUnpackedLiteralIncomplete(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = ["a", ...$rest];');
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

        self::assertFalse($environment->read('result')->soleArray()?->complete);
    }

    public function testReadElementFallsBackToTheOriginOfTheArray(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = $_POST["a"]["b"];');
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

        self::assertSame(Origin::External, $environment->read('result')->patterns()[0]->holes()[0]->origin);
    }

    public function testLookupFindsAnElementByItsKey(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $array = new ArrayTerm([
            new ArrayEntry(null, Domain::literal('first')),
            new ArrayEntry(Domain::literal('k'), Domain::literal('keyed')),
        ]);
        self::assertSame('first', $evaluator->lookup($array, 0)?->soleLiteral()?->value);
        self::assertSame('keyed', $evaluator->lookup($array, 'k')?->soleLiteral()?->value);
        self::assertNull($evaluator->lookup($array, 'missing'));
        self::assertNull($evaluator->lookup($array, true));
    }

    public function testOriginOfReportsExternalInputWhenAnyTermCarriesIt(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $external = Domain::opaque(TypeShape::unknown(), Origin::External);
        self::assertSame(Origin::External, $evaluator->originOf($external));
        self::assertSame(Origin::Unresolved, $evaluator->originOf(Domain::literal('a')));
    }

    public function testReadEnumPropertyOnlyAnswersForAnEnum(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; case B = "b"; } class C {}');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        $all = $evaluator->readEnumProperty(Domain::unknown(), 'S', 'value', $scope, $expressions);
        self::assertNotNull($all);
        self::assertCount(2, $all->terms);
        self::assertNull($evaluator->readEnumProperty(Domain::unknown(), 'C', 'value', $scope, $expressions));
        self::assertNull($evaluator->readEnumProperty(Domain::unknown(), 'S', 'other', $scope, $expressions));
    }

    public function testReadEnumPropertyNarrowsToOneCaseWhenTheCaseIsKnown(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; case B = "b"; } $result = S::A->value;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('a', $environment->read('result')->soleLiteral()?->value);
    }

    public function testReadEnumPropertyReadsCaseNames(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; } $result = S::A->name;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('A', $environment->read('result')->soleLiteral()?->value);
    }

    public function testReadPropertyFallsBackToTheDeclaredType(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { public string $t = "u"; public function set(): void { $this->t = "v"; } } $c = new C(); $result = $c->t;',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $environment = array_reduce(
            (new SliceExecutor())->run(
                array_map(static fn (Stmt $statement): SliceStep => new SliceStep($statement instanceof Stmt\Expression ? $statement->expr : $statement), $file->statements),
                new Environment(),
                new FunctionScope('t.php'),
                $expressions,
            ),
            static fn (?Environment $joined, Environment $run): Environment => $joined === null ? $run : $joined->join($run),
        ) ?? new Environment();

        self::assertSame('string', $environment->read('result')->type()->display());
        self::assertFalse($environment->read('result')->isExact());
    }

    public function testReadInstanceResolvesStaticToTheEnclosingClass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { public function make(): self { return new static(); } }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $node = new Expr\New_(new Name('static'));
        self::assertSame('C', $evaluator->readInstance($node, new FunctionScope('t.php', 'C::make', 'C'))->soleObject()?->className);
    }

    public function testReadInstanceGivesUpOnAnExpressionClass(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $node = new Expr\New_(new Variable('class'));
        self::assertSame('object', $evaluator->readInstance($node, new FunctionScope('t.php'))->type()->display());
    }

    public function testAssignWritesIntoAnArrayHeldByAVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $p = []; $p[] = "a"; $p[":id"] = 1; $result = $p[":id"];');
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

        self::assertSame(1, $environment->read('result')->soleLiteral()?->value);
        $array = $environment->read('p')->soleArray();
        self::assertNotNull($array);
        self::assertCount(2, $array->entries);
    }

    public function testAssignElementIgnoresATargetItCannotName(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $target = new ArrayDimFetch(new Variable(new Variable('name')));
        $evaluator->assignElement($target, Domain::literal('a'), $environment, new FunctionScope('t.php'), $expressions);
        self::assertSame([], $environment->names());
    }

    #[DataProvider('providerTrackedName')]
    public function testTrackedNameNamesAVariableOrAPropertyOfThis(Expr $target, ?string $expected): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());

        self::assertSame($expected, $evaluator->trackedName($target));
    }

    /**
     * @return array<string, array{Expr, string|null}>
     */
    public static function providerTrackedName(): array
    {
        return [
            'variable' => [new Variable('sql'), 'sql'],
            'variable variable' => [new Variable(new Variable('name')), null],
            'property of this' => [new PropertyFetch(new Variable('this'), 'table'), 'this->table'],
            'nullsafe property of this' => [new NullsafePropertyFetch(new Variable('this'), 'table'), 'this->table'],
            'property of another object' => [new PropertyFetch(new Variable('other'), 'table'), null],
            'dynamic property of this' => [new PropertyFetch(new Variable('this'), new Variable('name')), null],
            'property of a property' => [new PropertyFetch(new PropertyFetch(new Variable('this'), 'db'), 'table'), null],
            'element' => [new ArrayDimFetch(new Variable('parts')), null],
        ];
    }

    public function testAssignListWritesEachElementIntoTheTargetAtItsPosition(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $value = Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::literal('x')),
            new ArrayEntry(null, Domain::literal('y')),
            new ArrayEntry(null, Domain::literal('z')),
        ]));

        $evaluator->assignList(
            new List_([new ArrayItem(new Variable('a')), null, new ArrayItem(new PropertyFetch(new Variable('this'), 'b'))]),
            $value,
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertSame(['a', 'this->b'], $environment->names());
        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
        self::assertSame('z', $environment->read('this->b')->soleLiteral()?->value);
    }

    public function testAssignListReadsAKeyedTargetByItsKey(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $value = Domain::of(new ArrayTerm([
            new ArrayEntry(Domain::literal('first'), Domain::literal('x')),
            new ArrayEntry(Domain::literal('second'), Domain::literal('y')),
        ]));

        $evaluator->assignList(
            new Expr\Array_([
                new ArrayItem(new Variable('b'), new String_('second')),
                new ArrayItem(new Variable('a'), new String_('first')),
            ]),
            $value,
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
        self::assertSame('y', $environment->read('b')->soleLiteral()?->value);
    }

    public function testAssignListDescendsIntoANestedList(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $value = Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::of(new ArrayTerm([
                new ArrayEntry(null, Domain::literal('p')),
                new ArrayEntry(null, Domain::literal('q')),
            ]))),
            new ArrayEntry(null, Domain::literal('r')),
        ]));

        $evaluator->assignList(
            new List_([
                new ArrayItem(new List_([new ArrayItem(new Variable('a')), new ArrayItem(new Variable('b'))])),
                new ArrayItem(new Variable('c')),
            ]),
            $value,
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertSame('p', $environment->read('a')->soleLiteral()?->value);
        self::assertSame('q', $environment->read('b')->soleLiteral()?->value);
        self::assertSame('r', $environment->read('c')->soleLiteral()?->value);
    }

    public function testAssignListLeavesAGapCarryingTheOriginOfAValueThatIsNotAKnownArray(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $evaluator->assignList(
            new List_([new ArrayItem(new Variable('a'))]),
            Domain::opaque(TypeShape::unknown(), Origin::External),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        $hole = $environment->read('a')->patterns()[0]->holes()[0];
        self::assertSame(Origin::External, $hole->origin);
        self::assertSame('$a', $hole->expression);
    }

    public function testAssignListLeavesAGapForAnElementTheArrayDoesNotHold(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $evaluator->assignList(
            new List_([
                new ArrayItem(new Variable('a')),
                new ArrayItem(new Variable('b')),
                new ArrayItem(new Variable('c'), new Variable('key')),
            ]),
            Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('x'))])),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertSame('x', $environment->read('a')->soleLiteral()?->value);
        self::assertSame(Origin::Unresolved, $environment->read('b')->patterns()[0]->holes()[0]->origin);
        self::assertSame('$b', $environment->read('b')->patterns()[0]->holes()[0]->expression);
        self::assertSame('$c', $environment->read('c')->patterns()[0]->holes()[0]->expression);
    }

    public function testLoseTrackMarksTheArrayANestedWriteGoesIntoAsIncomplete(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $environment = new Environment([
            'parts' => Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('where'), Domain::literal('a = 1'))])),
        ]);

        $evaluator->loseTrack(new ArrayDimFetch(new ArrayDimFetch(new Variable('parts'), new String_('where')), new String_('and')), $environment);

        $array = $environment->read('parts')->soleArray();
        self::assertNotNull($array);
        self::assertFalse($array->complete);
        self::assertCount(1, $array->entries);
        self::assertSame('where', $array->entries[0]->key?->soleLiteral()?->value);
        self::assertSame('a = 1', $array->entries[0]->value->soleLiteral()?->value);
    }

    public function testLoseTrackMarksAnArrayHeldByAPropertyOfThisAsIncomplete(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $environment = new Environment(['this->parts' => Domain::of(new ArrayTerm([]))]);

        $evaluator->loseTrack(new PropertyFetch(new Variable('this'), 'parts'), $environment);

        self::assertFalse($environment->read('this->parts')->soleArray()?->complete);
    }

    public function testLoseTrackLeavesAnythingThatIsNotATrackedArrayAlone(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $environment = new Environment(['sql' => Domain::literal('SELECT 1')]);

        $evaluator->loseTrack(new ArrayDimFetch(new Variable('sql')), $environment);
        $evaluator->loseTrack(new ArrayDimFetch(new Variable('missing')), $environment);
        $evaluator->loseTrack(new ArrayDimFetch(new Variable(new Variable('name'))), $environment);

        self::assertSame(['sql'], $environment->names());
        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testEvaluateReadsAnInstantiationAStaticPropertyAndAClosure(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();
        $scope = new FunctionScope('t.php');

        $instance = $evaluator->evaluate(new Expr\New_(new Name('C')), $environment, $scope, $expressions);
        $static = $evaluator->evaluate(new Expr\StaticPropertyFetch(new Name('C'), 'table'), $environment, $scope, $expressions);
        $closure = $evaluator->evaluate(new Expr\Closure(), $environment, $scope, $expressions);
        $arrow = $evaluator->evaluate(new Expr\ArrowFunction(['expr' => new String_('a')]), $environment, $scope, $expressions);

        self::assertSame('C', $instance?->soleObject()?->className);
        self::assertSame(Origin::Property, $static?->patterns()[0]->holes()[0]->origin);
        self::assertSame('C::$table', $static->patterns()[0]->holes()[0]->expression);
        self::assertSame('Closure', $closure?->soleObject()?->className);
        self::assertSame('Closure', $arrow?->soleObject()?->className);
    }

    public function testReadVariableNamesTheExternalInputItReads(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());

        $hole = $evaluator->readVariable(new Variable('_GET'), new Environment(), new FunctionScope('t.php'))->patterns()[0]->holes()[0];

        self::assertSame(Origin::External, $hole->origin);
        self::assertSame('$_GET', $hole->expression);
    }

    public function testReadArrayKnowsALiteralWithoutUnpackingInFull(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $node = new Expr\Array_([new ArrayItem(new String_('a'))]);

        self::assertTrue($evaluator->readArray($node, new Environment(), new FunctionScope('t.php'), $expressions)->soleArray()?->complete);
    }

    public function testReadArrayKeepsTheElementsWrittenAfterAnUnpacking(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $node = new Expr\Array_([
            new ArrayItem(new Variable('rest'), null, false, [], true),
            new ArrayItem(new String_('a')),
        ]);

        $array = $evaluator->readArray($node, new Environment(), new FunctionScope('t.php'), $expressions)->soleArray();

        self::assertNotNull($array);
        self::assertFalse($array->complete);
        self::assertSame(['a'], array_map(static fn (ArrayEntry $entry): string|int|float|bool|null => $entry->value->soleLiteral()?->value, $array->entries));
    }

    public function testLookupNeverReadsAnElementUnderABooleanOrANullKey(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $array = new ArrayTerm([
            new ArrayEntry(null, Domain::literal('first')),
            new ArrayEntry(null, Domain::literal('second')),
            new ArrayEntry(Domain::literal(''), Domain::literal('empty')),
        ]);

        self::assertNull($evaluator->lookup($array, true));
        self::assertNull($evaluator->lookup($array, null));
    }

    public function testOriginOfIsTheOriginOfTheFirstGapUnlessAnyGapIsExternalInput(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $parameter = Domain::opaque(TypeShape::unknown(), Origin::Parameter);
        $property = Domain::opaque(TypeShape::of(['string']), Origin::Property);
        $external = Domain::opaque(TypeShape::of(['int']), Origin::External);

        self::assertSame(Origin::Parameter, $evaluator->originOf($parameter));
        self::assertSame(Origin::Parameter, $evaluator->originOf(Domain::literal('a')->union($parameter)->union($property)));
        self::assertSame(Origin::External, $evaluator->originOf($parameter->union($external)));
    }

    public function testReadPropertyPrefersWhatTheBodyAssignedEarlier(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { public string $t = "users"; function m(): void { $this->t; } }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $node = (new \PhpParser\NodeFinder())->findFirstInstanceOf($file->statements, PropertyFetch::class);
        self::assertInstanceOf(PropertyFetch::class, $node);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $scope = new FunctionScope('t.php', 'C::m', 'C');

        $assigned = $evaluator->readProperty($node, new Environment(['this->t' => Domain::literal('admins')]), $scope, $expressions);
        $declared = $evaluator->readProperty($node, new Environment(), $scope, $expressions);

        self::assertSame('admins', $assigned->soleLiteral()?->value);
        self::assertSame('users', $declared->soleLiteral()?->value);
    }

    public function testReadPropertyQuotesAPropertyItCannotName(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['c' => Domain::of(new \SqlCatalog\Evaluation\ObjectTerm('C'))]);
        $scope = new FunctionScope('t.php');

        $dynamic = $evaluator->readProperty(new PropertyFetch(new Variable('c'), new Variable('p')), $environment, $scope, $expressions);
        $unknown = $evaluator->readProperty(new PropertyFetch(new Variable('x'), 't'), $environment, $scope, $expressions);

        self::assertSame(Origin::Property, $dynamic->patterns()[0]->holes()[0]->origin);
        self::assertSame('$c->{$p}', $dynamic->patterns()[0]->holes()[0]->expression);
        self::assertSame('$x->t', $unknown->patterns()[0]->holes()[0]->expression);
    }

    public function testReadEnumPropertyReadsTheCaseTheValueIs(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; case B = "b"; case C = "c"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();

        $read = $evaluator->readEnumProperty(Domain::of(new \SqlCatalog\Evaluation\ObjectTerm('S', 'B')), 'S', 'value', new FunctionScope('t.php'), $expressions);

        self::assertSame('b', $read?->soleLiteral()?->value);
    }

    public function testReadEnumPropertyIgnoresAClassTheSourceDoesNotDeclare(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();

        self::assertNull($evaluator->readEnumProperty(Domain::unknown(), 'Missing', 'value', new FunctionScope('t.php'), $expressions));
    }

    public function testAssignLeavesATargetItDoesNotTrackAlone(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment();

        $evaluator->assign(new PropertyFetch(new Variable('other'), 'table'), Domain::literal('users'), $environment, new FunctionScope('t.php'), $expressions);
        $evaluator->assign(new Expr\StaticPropertyFetch(new Name('C'), 'table'), Domain::literal('users'), $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame([], $environment->names());
    }

    public function testAssignElementMarksTheArrayANestedWriteGoesIntoAsIncomplete(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['parts' => Domain::of(new ArrayTerm([]))]);

        $evaluator->assignElement(
            new ArrayDimFetch(new ArrayDimFetch(new Variable('parts'), new String_('where'))),
            Domain::literal('a = 1'),
            $environment,
            new FunctionScope('t.php'),
            $expressions,
        );

        self::assertFalse($environment->read('parts')->soleArray()?->complete);
    }

    public function testAssignElementKeepsWhetherTheArrayIsKnownInFull(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $environment = new Environment(['partial' => Domain::of(new ArrayTerm([], false))]);
        $scope = new FunctionScope('t.php');

        $evaluator->assignElement(new ArrayDimFetch(new Variable('fresh')), Domain::literal('a'), $environment, $scope, $expressions);
        $evaluator->assignElement(new ArrayDimFetch(new Variable('partial')), Domain::literal('a'), $environment, $scope, $expressions);

        self::assertTrue($environment->read('fresh')->soleArray()?->complete);
        self::assertFalse($environment->read('partial')->soleArray()?->complete);
    }
}
