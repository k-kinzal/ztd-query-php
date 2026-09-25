<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Model\ModelQueries;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\Model\QueryModelInterface;
use SqlCatalog\Core\Extension\Model\QueryOutput;

#[CoversClass(ModelQueries::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\CallEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ConstantReader::class)]
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
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Solution::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExpressionEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Interpreter::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ReferenceEvaluator::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkFinder::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\SinkMatcher::class)]
#[UsesClass(Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextHole::class)]
#[UsesClass(\SqlCatalog\Core\Text\TextPattern::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Binding::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Arrival::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\Pending::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\ExternalInput::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionScope::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Environment::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(QueryOutput::class)]
#[UsesClass(\SqlCatalog\Core\Php\ParsedFile::class)]
#[UsesClass(\SqlCatalog\Core\Php\SourceParser::class)]
#[UsesClass(\SqlCatalog\Core\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\BuiltinCallModel::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
final class ModelQueriesTest extends TestCase
{
    public function testSolveRetainsMissingModelsAsIncompleteStatements(): void
    {
        $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('run'));
        $deriver = (new \SqlCatalog\Core\Analysis\Interpreter(new \SqlCatalog\Core\Php\ProgramIndex(), []))->deriverFor([]);
        $solutions = (new ModelQueries())->solve($call, null, $deriver);
        self::assertCount(1, $solutions);
        self::assertFalse($solutions[0]->values[0]->isExact());
        self::assertSame('Statement model is not registered', $solutions[0]->values[0]->patterns()[0]->holes()[0]->expression);
    }

    public function testSolveKeepsCompilerFlagsAndMultipleOutputs(): void
    {
        $file = (new \SqlCatalog\Core\Php\SourceParser())->parse('query.php', '<?php $table = "users"; run($table);');
        $call = (new \PhpParser\NodeFinder())->findInstanceOf($file->statements, \PhpParser\Node\Expr\FuncCall::class)[0];
        $deriver = (new \SqlCatalog\Core\Analysis\Interpreter(new \SqlCatalog\Core\Php\ProgramIndex(), []))->deriverFor([$file]);
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
