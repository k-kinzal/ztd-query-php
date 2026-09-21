<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\ConstantReader;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Analysis\Interpreter;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Php\ProgramIndexBuilder;
use SqlCatalog\Php\SourceParser;

#[CoversClass(ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(FunctionScope::class)]
#[UsesClass(Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(ProgramIndex::class)]
#[UsesClass(ProgramIndexBuilder::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
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
        $node = new \PhpParser\Node\Expr\ClassConstFetch(new Variable('c'), 'T');
        $read = $evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions);
        self::assertSame('mixed', $read->type()->display());
    }

    public function testReadClassConstantGivesUpOnAConstantNothingDeclares(): void
    {
        $evaluator = new ConstantReader(new ProgramIndex(), new NodeText());
        $expressions = (new Interpreter(new ProgramIndex(), []))->evaluatorFor();
        $node = new \PhpParser\Node\Expr\ClassConstFetch(new Name('C'), 'MISSING');
        self::assertFalse($evaluator->readClassConstant($node, new FunctionScope('t.php'), $expressions)->isExact());
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
