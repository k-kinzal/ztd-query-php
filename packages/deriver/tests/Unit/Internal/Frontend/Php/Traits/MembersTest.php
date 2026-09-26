<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Traits;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Traits\Members
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[Small]
final class MembersTest extends TestCase
{
    public function testCandidatesUsesImmediateTraitNamesForAdaptations(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{function f(){}}trait U{function f(){}}');
        $class = new \Deriver\Internal\IR\ClassDeclaration('B', traits:['T','U']);
        $methods = (new \Deriver\Internal\Frontend\Php\Traits\Members($index))->candidates($class);
        self::assertSame(['t','u'], array_keys($methods['f']));
    }
    public function testPrecedenceRemovesOnlyExplicitlyExcludedVariants(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{function f(){}}trait U{function f(){}}');
        $members = new \Deriver\Internal\Frontend\Php\Traits\Members($index);
        $candidates = $members->candidates(new \Deriver\Internal\IR\ClassDeclaration('B', traits:['T','U']));
        $adaptation = new \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence(new \PhpParser\Node\Name('T'), 'f', [new \PhpParser\Node\Name('U')]);
        self::assertSame(['t'], array_keys($members->precedence($candidates, [$adaptation])['f']));
        self::assertSame(['t','u'], array_keys($candidates['f']));
    }
    public function testAliasesKeepsTheOriginalAlongsideAnExcludedTraitAlias(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U{T::f insteadof U;U::f as protected g;}}');
        $body = $index->callable('B::g');
        self::assertNotNull($body);
        self::assertSame('protected', $body->visibility);
        self::assertSame('B', $body->className);
        self::assertSame(['f','g'], array_keys($index->classes()['b']->methods));
    }
    public function testSelectReportsUnresolvedConcreteConflicts(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{function f(){}}trait U{function f(){}}class B{use T,U;}');
        self::assertSame('INVALID_PROGRAM', $index->diagnostics()[0]->code);
        self::assertSame([], $index->classes()['b']->methods);
    }
}
