<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
#[UsesClass(\SqlCatalog\Analysis\BodyWalker::class)]
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
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\ValueBinder::class)]
final class ReferenceEvaluatorTest extends TestCase
{
    #[DataProvider('providerEvaluate')]
    public function testEvaluate(string $code, string $expected): void
    {
        $file = (new SourceParser())->parse('t.php', $code);
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
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
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\Array_::class, $node);

        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $read = $evaluator->readArray($node, new Environment(), new FunctionScope('t.php'), $expressions);

        $array = $read->soleArray();
        self::assertNotNull($array);
        self::assertCount(2, $array->entries);
    }

    public function testReadElementIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php ["a", "b"][1];');
        $statement = $file->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(ArrayDimFetch::class, $node);

        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $read = $evaluator->readElement($node, new Environment(), new FunctionScope('t.php'), $expressions);

        self::assertSame('b', $read->soleLiteral()?->value);
    }

    public function testReadPropertyIsCalledWithTheExpressionItself(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { public string $t = "users"; } $c = new C(); $c->t;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $statement = $file->statements[2];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statement);
        $node = $statement->expr;
        self::assertInstanceOf(\PhpParser\Node\Expr\PropertyFetch::class, $node);

        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment(['c' => Domain::of(new \SqlCatalog\Evaluation\ObjectTerm('C'))]);
        $read = $evaluator->readProperty($node, $environment, new FunctionScope('t.php'), $expressions);

        self::assertSame('users', $read->soleLiteral()?->value);
    }

    public function testEvaluateOnlyAnswersForReferences(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
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
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertFalse($environment->read('result')->soleArray()?->complete);
    }

    public function testReadElementFallsBackToTheOriginOfTheArray(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $result = $_POST["a"]["b"];');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

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
        $external = Domain::opaque(\SqlCatalog\Type\TypeShape::unknown(), Origin::External);
        self::assertSame(Origin::External, $evaluator->originOf($external));
        self::assertSame(Origin::Unresolved, $evaluator->originOf(Domain::literal('a')));
    }

    public function testReadRuntimeConstantOnlyTrustsThePhpConstants(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        self::assertSame(PHP_INT_MAX, $evaluator->readRuntimeConstant('PHP_INT_MAX')->soleLiteral()?->value);
        self::assertNull($evaluator->readRuntimeConstant('DIRECTORY_SEPARATOR')->soleLiteral());
    }

    public function testConstantNamePrefersTheOneTheNamespaceDeclares(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; const T = "x"; $result = T;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        self::assertSame('T', $evaluator->constantName(new ConstFetch(new Name('T'))));
    }

    public function testReadConstantResolvesTheKeywordsAndTheDeclarations(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php const T = "users";');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
        $scope = new FunctionScope('t.php');

        self::assertTrue($evaluator->readConstant(new ConstFetch(new Name('true')), $scope, $expressions)->soleLiteral()?->value);
        self::assertFalse($evaluator->readConstant(new ConstFetch(new Name('false')), $scope, $expressions)->soleLiteral()?->value);
        self::assertNull($evaluator->readConstant(new ConstFetch(new Name('null')), $scope, $expressions)->soleLiteral()?->value);
        self::assertSame('users', $evaluator->readConstant(new ConstFetch(new Name('T')), $scope, $expressions)->soleLiteral()?->value);
    }

    public function testReadClassConstantGivesUpWhenTheClassIsNotWritten(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $node = new \PhpParser\Node\Expr\ClassConstFetch(new Variable('c'), 'T');
        $read = $evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions);
        self::assertSame('mixed', $read->type()->display());
    }

    public function testReadClassConstantGivesUpOnAConstantNothingDeclares(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $node = new \PhpParser\Node\Expr\ClassConstFetch(new Name('C'), 'MISSING');
        self::assertFalse($evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions)->isExact());
    }

    public function testReadEnumPropertyOnlyAnswersForAnEnum(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; case B = "b"; } class C {}');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
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
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('a', $environment->read('result')->soleLiteral()?->value);
    }

    public function testReadEnumPropertyReadsCaseNames(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; } $result = S::A->name;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('A', $environment->read('result')->soleLiteral()?->value);
    }

    public function testReadPropertyFallsBackToTheDeclaredType(): void
    {
        $file = (new SourceParser())->parse(
            't.php',
            '<?php class C { public string $t = "u"; public function set(): void { $this->t = "v"; } } $c = new C(); $result = $c->t;',
        );
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame('string', $environment->read('result')->type()->display());
        self::assertFalse($environment->read('result')->isExact());
    }

    public function testReadInstanceResolvesStaticToTheEnclosingClass(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { public function make(): self { return new static(); } }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ReferenceEvaluator($index, new ExternalInput(), new NodeText());
        $node = new \PhpParser\Node\Expr\New_(new Name('static'));
        self::assertSame('C', $evaluator->readInstance($node, new FunctionScope('t.php', 'C::make', 'C'))->soleObject()?->className);
    }

    public function testReadInstanceGivesUpOnAnExpressionClass(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $node = new \PhpParser\Node\Expr\New_(new Variable('class'));
        self::assertSame('object', $evaluator->readInstance($node, new FunctionScope('t.php'))->type()->display());
    }

    public function testResolveClassNameResolvesTheSelfKeywords(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $scope = new FunctionScope('t.php', 'C::m', 'C');
        self::assertSame('C', $evaluator->resolveClassName(new Name('self'), $scope));
        self::assertSame('Other', $evaluator->resolveClassName(new Name('Other'), $scope));
        self::assertNull($evaluator->resolveClassName(new Variable('c'), $scope));
    }

    public function testAssignWritesIntoAnArrayHeldByAVariable(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php $p = []; $p[] = "a"; $p[":id"] = 1; $result = $p[":id"];');
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $expressions->bodies()->walk($file->statements, $environment, new FunctionScope('t.php'));

        self::assertSame(1, $environment->read('result')->soleLiteral()?->value);
        $array = $environment->read('p')->soleArray();
        self::assertNotNull($array);
        self::assertCount(2, $array->entries);
    }

    public function testAssignElementIgnoresATargetItCannotName(): void
    {
        $evaluator = new ReferenceEvaluator(new ProgramIndex(), new ExternalInput(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor(new StatementRecorder());
        $environment = new Environment();
        $target = new ArrayDimFetch(new Variable(new Variable('name')));
        $evaluator->assignElement($target, Domain::literal('a'), $environment, new FunctionScope('t.php'), $expressions);
        self::assertSame([], $environment->names());
    }
}
