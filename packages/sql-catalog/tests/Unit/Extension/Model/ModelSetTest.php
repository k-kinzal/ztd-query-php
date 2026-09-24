<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\Model\CallContext;
use SqlCatalog\Extension\Model\ModelSet;
use SqlCatalog\Extension\Model\QueryModelInterface;

#[CoversClass(ModelSet::class)]
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
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(CallContext::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
final class ModelSetTest extends TestCase
{
    public function testMergePreservesCallOrderAndReplacesStatementKeys(): void
    {
        $first = self::createStub(QueryModelInterface::class);
        $second = self::createStub(QueryModelInterface::class);
        $call = static fn (CallContext $context): ?Domain => null;
        $relation = static fn (string $class, string $expected): bool => $class === 'Concrete' && $expected === 'Contract';
        $merged = (new ModelSet([$call], ['first' => $first, 'shared' => $first]))->merge(new ModelSet([$call], ['shared' => $second], [$relation]));
        self::assertSame([$call, $call], $merged->calls);
        self::assertSame(['first' => $first, 'shared' => $second], $merged->queries);
        self::assertTrue($merged->matchesClass('Concrete', 'Contract'));
    }

    public function testEvaluateUsesTheFirstHandlingFunctionAndPreservesItsDomain(): void
    {
        $value = Domain::unknown('unresolved fragment')->concat(Domain::literal(' WHERE id = ?'));
        $context = new CallContext(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('sql')), [], new \SqlCatalog\Evaluation\Environment(), new \SqlCatalog\Analysis\FunctionScope('query.php'), (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->evaluatorFor());
        $models = new ModelSet([
            static fn (CallContext $call): ?Domain => null,
            static fn (CallContext $call): Domain => $value,
            static fn (CallContext $call): Domain => Domain::literal('wrong'),
        ]);
        self::assertSame($value, $models->evaluate($context));
        self::assertNull((new ModelSet())->evaluate($context));
    }

    public function testMatchesClassFallsThroughRelationsWithoutClaimingUnknownTypes(): void
    {
        $models = new ModelSet(classRelations: [static fn (string $class, string $expected): bool => false, static fn (string $class, string $expected): bool => $class === 'Known' && $expected === 'Base']);
        self::assertTrue($models->matchesClass('Known', 'Base'));
        self::assertFalse($models->matchesClass('Other', 'Base'));
        self::assertFalse((new ModelSet())->matchesClass('Known', 'Base'));
    }
}
