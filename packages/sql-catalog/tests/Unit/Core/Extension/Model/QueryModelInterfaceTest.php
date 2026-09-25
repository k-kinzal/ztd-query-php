<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\Model\QueryModelInterface;

#[CoversClass(QueryModelInterface::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CalleeReturns::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\CallerIndex::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Callers::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Deriver::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\EntryBinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\PropertyWrites::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Model\ModelQueries::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\QueryOutput::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
final class QueryModelInterfaceTest extends TestCase
{
    public function testInputsCanBeProvidedOutsideTheBuiltins(): void
    {
        $model = self::createStub(QueryModelInterface::class);
        $model->method('inputs')->willReturn([]);
        $model->method('statements')->willReturn([new \SqlCatalog\Core\Extension\Model\QueryOutput(\SqlCatalog\Core\Evaluation\Domain::literal('SELECT 1'), \SqlCatalog\Core\Evaluation\Domain::literal(null))]);
        $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run'));
        $deriver = (new \SqlCatalog\Core\Analysis\Interpreter(new \SqlCatalog\Core\Php\ProgramIndex(), []))->deriverFor([]);
        self::assertSame('SELECT 1', (new \SqlCatalog\Core\Analysis\Model\ModelQueries())->solve($call, $model, $deriver)[0]->values[0]->soleLiteral()?->value);
    }
    public function testStatementsCanPreserveUnresolvedSqlFragments(): void
    {
        $model = self::createStub(QueryModelInterface::class);
        $sql = \SqlCatalog\Core\Evaluation\Domain::unknown('table');
        $model->method('statements')->willReturn([new \SqlCatalog\Core\Extension\Model\QueryOutput($sql, \SqlCatalog\Core\Evaluation\Domain::literal(null))]);
        self::assertSame($sql, $model->statements(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run')), [])[0]->sql);
    }

}
