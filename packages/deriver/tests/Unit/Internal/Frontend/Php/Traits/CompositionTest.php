<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Traits;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Traits\Composition
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\LexicalConstants::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Members::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\PropertyScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CompositionTest extends TestCase
{
    public function testComposeImportsNestedPrivateMembersIntoTheConsumer(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait U{private $x=4;function f(){return $this->x;}}trait T{use U;}class B{use T;}');
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertTrue($index->classes()['b']->composed);
    }
    public function testImportPreservesClassMethodPrecedence(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{function f(){return 1;}}class B{use T;function f(){return 2;}}');
        self::assertSame([], $index->diagnostics());
        self::assertSame('B::f', $index->classes()['b']->methods['f']);
        self::assertSame(['T'], $index->classes()['b']->traits);
    }
    public function testPropertiesCreatesSeparatePrivateSlotsForEachConsumer(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{private $x=1;}class A{use T;}class B{use T;}');
        self::assertSame('A', $index->classes()['a']->properties['x']->className);
        self::assertSame('B', $index->classes()['b']->properties['x']->className);
    }
    public function testConstantsRetainsUnevaluatedInitializersInConsumerScope(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php trait T{const C=self::D;}class B{use T;const D=4;}');
        $constant = $index->constant('B::C');
        self::assertNotNull($constant);
        self::assertSame('B', $constant->className);
    }
}
