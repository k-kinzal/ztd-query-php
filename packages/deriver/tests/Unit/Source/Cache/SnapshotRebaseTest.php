<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Cache;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Reference\SourceRef;
use Deriver\Source\Cache\SnapshotRebase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Cache\SnapshotRebase
 */
#[CoversClass(SnapshotRebase::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SnapshotRebaseTest extends TestCase
{
    public function testCallableRebasesDefaultExpressionsWithTheOwningBody(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target($value=3){return $value;}');
        $body = $index->callable('target');
        self::assertInstanceOf(CallableGraph::class, $body);
        $rebased = (new SnapshotRebase('new'))->callable($body);
        self::assertSame('new', $rebased->source->snapshotId);
        self::assertSame('new', $rebased->parameters[0]->default?->source->snapshotId);
        self::assertSame('test', $body->source->snapshotId);
    }
    public function testCallableKeepsTheRawDocComment(): void
    {
        $body = \Tests\Fake\SourceFixture::index('<?php /** @return int */ function target(){return 1;}')->callable('target');
        self::assertInstanceOf(CallableGraph::class, $body);
        self::assertSame('/** @return int */', (new SnapshotRebase('new'))->callable($body)->docComment);
    }
    public function testInstructionPreservesOrderedOperandsAndItsDefinitionIdentity(): void
    {
        $source = new SourceRef('old', 'a.php', 5, 8, 2, 3);
        $instruction = new Instruction('id', 'binary', $source, 'r3', ['r1','r2'], '+');
        $rebased = (new SnapshotRebase('new'))->instruction($instruction);
        self::assertSame('new', $rebased->source->snapshotId);
        self::assertSame(['r1','r2'], $rebased->operands);
        self::assertSame('id', $rebased->id);
    }
    public function testSourcePreservesExactByteAndDisplayCoordinates(): void
    {
        $source = new SourceRef('old', 'a.php', 5, 8, 2, 3);
        $rebased = (new SnapshotRebase('new'))->source($source);
        self::assertSame(['new','a.php',5,8,2,3], [$rebased->snapshotId,$rebased->path,$rebased->start,$rebased->end,$rebased->line,$rebased->column]);
    }
}
