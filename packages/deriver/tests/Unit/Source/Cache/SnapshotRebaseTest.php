<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Cache;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Cache\SnapshotRebase
 */
#[CoversClass(SnapshotRebase::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Term::class)]
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
