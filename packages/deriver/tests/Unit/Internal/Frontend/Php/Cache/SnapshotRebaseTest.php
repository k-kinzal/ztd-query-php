<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Cache\SnapshotRebase
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
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
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SnapshotRebaseTest extends TestCase
{
    public function testCallableRebasesDefaultExpressionsWithTheOwningBody(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target($value=3){return $value;}');
        $body = $index->callable('target');
        self::assertInstanceOf(\Deriver\Internal\IR\CallableIR::class, $body);
        $rebased = (new \Deriver\Internal\Frontend\Php\Cache\SnapshotRebase('new'))->callable($body);
        self::assertSame('new', $rebased->source->snapshotId);
        self::assertSame('new', $rebased->parameters[0]->default?->source->snapshotId);
        self::assertSame('test', $body->source->snapshotId);
    }
    public function testInstructionPreservesOrderedOperandsAndItsDefinitionIdentity(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('old', 'a.php', 5, 8, 2, 3);
        $instruction = new \Deriver\Internal\IR\Instruction('id', 'binary', $source, 'r3', ['r1','r2'], '+');
        $rebased = (new \Deriver\Internal\Frontend\Php\Cache\SnapshotRebase('new'))->instruction($instruction);
        self::assertSame('new', $rebased->source->snapshotId);
        self::assertSame(['r1','r2'], $rebased->operands);
        self::assertSame('id', $rebased->id);
    }
    public function testSourcePreservesExactByteAndDisplayCoordinates(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('old', 'a.php', 5, 8, 2, 3);
        $rebased = (new \Deriver\Internal\Frontend\Php\Cache\SnapshotRebase('new'))->source($source);
        self::assertSame(['new','a.php',5,8,2,3], [$rebased->snapshotId,$rebased->path,$rebased->start,$rebased->end,$rebased->line,$rebased->column]);
    }
}
