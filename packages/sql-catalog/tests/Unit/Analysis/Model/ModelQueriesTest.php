<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Model\ModelQueries;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\Model\QueryModelInterface;
use SqlCatalog\Extension\Model\QueryOutput;

#[CoversClass(ModelQueries::class)]
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
#[UsesClass(\SqlCatalog\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Analysis\SinkMatcher::class)]
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(QueryOutput::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Effect\WriteEffects::class)]
final class ModelQueriesTest extends TestCase
{
    public function testSolveRetainsMissingModelsAsIncompleteStatements(): void
    {
        $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run'));
        $deriver = (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->deriverFor([]);
        $solutions = (new ModelQueries())->solve($call, null, $deriver);
        self::assertCount(1, $solutions);
        self::assertFalse($solutions[0]->values[0]->isExact());
        self::assertSame('Statement model is not registered', $solutions[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testSolveKeepsCompilerFlagsAndMultipleOutputs(): void
    {
        $file = (new \SqlCatalog\Php\SourceParser())->parse('query.php', '<?php $table = "users"; run($table);');
        $call = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\FuncCall::class)[0];
        $deriver = (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->deriverFor([$file]);
        $model = new class () implements QueryModelInterface {
            public function inputs(\PhpParser\Node\Expr\CallLike $call): array
            {
                return [$call->getArgs()[0]->value];
            }

            public function statements(\PhpParser\Node\Expr\CallLike $call, array $values): array
            {
                return [new QueryOutput(Domain::literal('SELECT * FROM ')->concat($values[0]), Domain::literal(null), true), new QueryOutput(Domain::literal('SELECT 2'), Domain::literal(null), combined: true)];
            }
        };
        $solutions = (new ModelQueries())->solve($call, $model, $deriver);
        self::assertSame('SELECT * FROM users', $solutions[0]->values[0]->soleLiteral()?->value);
        self::assertTrue($solutions[0]->truncated);
        self::assertFalse($solutions[0]->combined);
        self::assertSame('SELECT 2', $solutions[1]->values[0]->soleLiteral()?->value);
        self::assertFalse($solutions[1]->truncated);
        self::assertTrue($solutions[1]->combined);
    }
}
