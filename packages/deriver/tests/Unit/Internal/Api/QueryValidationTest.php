<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Api\QueryValidation
 */
#[CoversClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class QueryValidationTest extends TestCase
{
    public function testOwnerRejectsAnEmptyReturnSymbol(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Api\QueryValidation(\Tests\Fake\SolverFixture::context()->program, 'test'))->owner(new \Deriver\Api\Query\ReturnQuery(''));
    }
    public function testReferenceRejectsForeignSnapshots(): void
    {
        $reference = new \Deriver\Api\Reference\ExpressionRef(new \Deriver\Api\Reference\SourceRef('foreign', 'fixture.php', 0, 1), 'target', 'r0');
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Api\QueryValidation(\Tests\Fake\SolverFixture::context()->program, 'test'))->reference($reference);
    }
    public function testReferenceAcceptsAnActualCapturedInstruction(): void
    {
        $program = \Tests\Fake\SolverFixture::context()->program;
        $body = $program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $reference = new \Deriver\Api\Reference\ExpressionRef($instruction->source, 'target', $instruction->result);
        $validation = new \Deriver\Internal\Api\QueryValidation($program, 'test');
        $validation->reference($reference);
        self::assertSame('target', $validation->owner(new \Deriver\Api\Query\ValueQuery($reference)));
    }
}
