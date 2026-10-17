<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Lowering;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Reference\SourceRef;
use Deriver\Source\Lowering\SnapshotRebase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Lowering\SnapshotRebase
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
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
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
