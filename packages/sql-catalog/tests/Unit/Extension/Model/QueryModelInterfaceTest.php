<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Model\QueryModelInterface;

#[CoversClass(QueryModelInterface::class)]
#[UsesClass(\SqlCatalog\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Deriver::class)]
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
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
final class QueryModelInterfaceTest extends TestCase
{
    public function testInputsCanBeProvidedOutsideTheBuiltins(): void
    {
        $model = self::createStub(QueryModelInterface::class);
        $model->method('inputs')->willReturn([]);
        $model->method('statements')->willReturn([new \SqlCatalog\Extension\Model\QueryOutput(\SqlCatalog\Evaluation\Domain::literal('SELECT 1'), \SqlCatalog\Evaluation\Domain::literal(null))]);
        $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run'));
        $deriver = (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->deriverFor([]);
        self::assertSame('SELECT 1', (new \SqlCatalog\Analysis\Model\ModelQueries())->solve($call, $model, $deriver)[0]->values[0]->soleLiteral()?->value);
    }
    public function testStatementsCanPreserveUnresolvedSqlFragments(): void
    {
        $model = self::createStub(QueryModelInterface::class);
        $sql = \SqlCatalog\Evaluation\Domain::unknown('table');
        $model->method('statements')->willReturn([new \SqlCatalog\Extension\Model\QueryOutput($sql, \SqlCatalog\Evaluation\Domain::literal(null))]);
        self::assertSame($sql, $model->statements(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run')), [])[0]->sql);
    }

}
