<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Cache;

use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Declaration\ProjectIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Cache\GraphTemplate
 */
#[CoversClass(GraphTemplate::class)]
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
#[UsesClass(GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[Small]
final class GraphTemplateTest extends TestCase
{
    public function testCapturedClosureDeclarationsRemainAvailableForRebasing(): void
    {
        $syntax = new SyntaxCache();
        $graphs = new GraphCache();
        $input = new ProjectInput([new SourceFile('a.php', '<?php function target(){return fn()=>7;}')]);
        $profile = new TargetProfile();
        $first = new ProjectIndex('first', $input, $profile, $syntax, $graphs);

        $first->callable('target');
        $template = array_values($graphs->records)[0];
        self::assertSame('target', $template->body->symbol);
        self::assertCount(1, $template->closures);
    }
}
