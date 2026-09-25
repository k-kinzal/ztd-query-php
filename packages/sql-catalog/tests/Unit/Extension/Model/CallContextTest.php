<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Model\CallContext;

#[CoversClass(CallContext::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
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
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class CallContextTest extends TestCase
{
    public function testKeepsTheModelContractInputs(): void
    {
        $node = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('fragment'));
        $environment = new \SqlCatalog\Evaluation\Environment();
        $context = new CallContext($node, [], $environment, new \SqlCatalog\Analysis\FunctionScope('query.php'), (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->evaluatorFor());
        self::assertSame($node, $context->node);
        self::assertSame($environment, $context->environment);
        self::assertNull($context->sink);
    }
}
