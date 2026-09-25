<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\ConstantReader;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Analysis\Interpreter;
use SqlCatalog\Core\Php\NodeText;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Php\ProgramIndexBuilder;
use SqlCatalog\Core\Php\SourceParser;

#[CoversClass(ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
#[UsesClass(\SqlCatalog\Core\Php\ClassShape::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
final class ConstantReaderTest extends TestCase
{
    public function testReadRuntimeConstantOnlyTrustsThePhpConstants(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        self::assertSame(PHP_INT_MAX, $evaluator->readRuntimeConstant('PHP_INT_MAX')->soleLiteral()?->value);
        self::assertNull($evaluator->readRuntimeConstant('DIRECTORY_SEPARATOR')->soleLiteral());
    }

    public function testConstantNamePrefersTheOneTheNamespaceDeclares(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; const T = "x"; $result = T;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ConstantReader($index, new NodeText());
        self::assertSame('T', $evaluator->constantName(new ConstFetch(new Name('T'))));
    }

    public function testReadConstantResolvesTheKeywordsAndTheDeclarations(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php const T = "users";');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ConstantReader($index, new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        self::assertTrue($evaluator->readConstant(new ConstFetch(new Name('true')), $scope, $expressions)->soleLiteral()?->value);
        self::assertFalse($evaluator->readConstant(new ConstFetch(new Name('false')), $scope, $expressions)->soleLiteral()?->value);
        self::assertNull($evaluator->readConstant(new ConstFetch(new Name('null')), $scope, $expressions)->soleLiteral()?->value);
        self::assertSame('users', $evaluator->readConstant(new ConstFetch(new Name('T')), $scope, $expressions)->soleLiteral()?->value);
    }

    public function testReadClassConstantGivesUpWhenTheClassIsNotWritten(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $node = new ClassConstFetch(new Variable('c'), 'T');
        $read = $evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions);
        self::assertSame('mixed', $read->type()->display());
    }

    public function testReadClassConstantGivesUpOnAConstantNothingDeclares(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $node = new ClassConstFetch(new Name('C'), 'MISSING');
        self::assertFalse($evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions)->isExact());
    }

    public function testReadConstantReadsTheKeywordsWhateverTheirCase(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        self::assertTrue($evaluator->readConstant(new ConstFetch(new Name('TRUE')), $scope, $expressions)->soleLiteral()?->value);
        self::assertFalse($evaluator->readConstant(new ConstFetch(new Name('False')), $scope, $expressions)->soleLiteral()?->value);
        self::assertTrue($evaluator->readConstant(new ConstFetch(new Name('NULL')), $scope, $expressions)->isExact());
        self::assertTrue($evaluator->readConstant(new ConstFetch(new Name('null')), $scope, $expressions)->isExact());
    }

    public function testConstantNameReadsTheNamespacedConstantOnlyWhenTheNamespaceDeclaresIt(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php namespace App; const T = "x"; $a = T; $b = PHP_EOL;');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $fetches = (new NodeFinder())->findInstanceOf($file->statements, ConstFetch::class);

        $names = array_map(
            static fn (ConstFetch $fetch): string => (new ConstantReader($index, new NodeText()))->constantName($fetch),
            $fetches,
        );

        self::assertSame(['App\\T', 'PHP_EOL'], $names);
    }

    public function testReadRuntimeConstantReadsAFullyQualifiedName(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());

        self::assertSame(PHP_INT_MAX, $evaluator->readRuntimeConstant('\\PHP_INT_MAX')->soleLiteral()?->value);
        self::assertSame('SQL_CATALOG_UNDEFINED', $evaluator->readRuntimeConstant('\\SQL_CATALOG_UNDEFINED')->patterns()[0]->holes()[0]->expression);
    }

    public function testReadClassConstantReadsWhatTheClassDeclares(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php class C { const T = "users"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $node = new ClassConstFetch(new Name('C'), 'T');

        self::assertSame('users', (new ConstantReader($index, new NodeText()))->readClassConstant($node, new FunctionScope('t.php'), $expressions)->soleLiteral()?->value);
    }

    public function testReadClassConstantReadsTheClassKeywordWhateverItsCase(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $scope = new FunctionScope('t.php', 'C::m', 'C');

        self::assertSame('App\\C', $evaluator->readClassConstant(new ClassConstFetch(new Name('App\\C'), 'class'), $scope, $expressions)->soleLiteral()?->value);
        self::assertSame('C', $evaluator->readClassConstant(new ClassConstFetch(new Name('self'), 'CLASS'), $scope, $expressions)->soleLiteral()?->value);
    }

    public function testReadClassConstantTellsAnEnumCaseFromAConstantOfTheEnum(): void
    {
        $file = (new SourceParser())->parse('t.php', '<?php enum S: string { case A = "a"; const X = "x"; }');
        $index = (new ProgramIndexBuilder())->build([$file]);
        $evaluator = new ConstantReader($index, new NodeText());
        $expressions = (new Interpreter($index, []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        $case = $evaluator->readClassConstant(new ClassConstFetch(new Name('S'), 'A'), $scope, $expressions)->soleObject();
        $constant = $evaluator->readClassConstant(new ClassConstFetch(new Name('S'), 'X'), $scope, $expressions);

        self::assertSame('S', $case?->className);
        self::assertSame('A', $case->enumCase);
        self::assertSame('x', $constant->soleLiteral()?->value);
    }

    public function testReadClassConstantQuotesWhatItCouldNotRead(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $scope = new FunctionScope('t.php');

        $missing = $evaluator->readClassConstant(new ClassConstFetch(new Name('C'), 'MISSING'), $scope, $expressions);
        $unwritten = $evaluator->readClassConstant(new ClassConstFetch(new Variable('c'), 'T'), $scope, $expressions);

        self::assertSame('C::MISSING', $missing->patterns()[0]->holes()[0]->expression);
        self::assertSame('$c::T', $unwritten->patterns()[0]->holes()[0]->expression);
    }

    public function testResolveClassNameResolvesEveryKeywordForTheEnclosingClass(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $scope = new FunctionScope('t.php', 'C::m', 'C');

        self::assertSame('C', $evaluator->resolveClassName(new Name('static'), $scope));
        self::assertSame('C', $evaluator->resolveClassName(new Name('parent'), $scope));
        self::assertSame('C', $evaluator->resolveClassName(new Name('SELF'), $scope));
    }

    public function testResolveClassNameResolvesTheSelfKeywords(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $scope = new FunctionScope('t.php', 'C::m', 'C');
        self::assertSame('C', $evaluator->resolveClassName(new Name('self'), $scope));
        self::assertSame('Other', $evaluator->resolveClassName(new Name('Other'), $scope));
        self::assertNull($evaluator->resolveClassName(new Variable('c'), $scope));
    }
}
