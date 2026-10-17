<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Lowering;

use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Lowering\LoweredCallable;
use Deriver\Source\Lowering\LoweringCache;
use Deriver\Source\Parsing\ParseCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Lowering\LoweredCallable
 */
#[CoversClass(LoweredCallable::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\CallLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[Small]
final class LoweredCallableTest extends TestCase
{
    public function testCapturedClosureDeclarationsRemainAvailableForRebasing(): void
    {
        $syntax = new ParseCache();
        $graphs = new LoweringCache();
        $input = new ProjectInput([new SourceFile('a.php', '<?php function target(){return fn()=>7;}')]);
        $profile = new TargetProfile();
        $first = new ProjectIndex('first', $input, $profile, $syntax, $graphs);

        $first->callable('target');
        $template = array_values($graphs->records)[0];
        self::assertSame('target', $template->body->symbol);
        self::assertCount(1, $template->closures);
    }
}
