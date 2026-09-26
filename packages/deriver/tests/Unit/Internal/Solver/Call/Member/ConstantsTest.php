<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Member;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Member\Constants
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ConstantsTest extends TestCase
{
    public function testFindRetainsTheDeclaringClassOfAnInheritedExpression(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Base{protected const A=1+2;}class Child extends Base{}');
        $constant = (new \Deriver\Internal\Solver\Call\Member\Constants($context->program))->find('Child', 'A');
        self::assertSame('Base', $constant?->className);
        self::assertSame('protected', $constant->visibility);
    }
    public function testAllowedUsesTheLexicalClassAndVisibility(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Base{}class Child extends Base{}');
        $lookup = new \Deriver\Internal\Solver\Call\Member\Constants($context->program);
        self::assertTrue($lookup->allowed(new \Deriver\Internal\IR\ClassConstant('Base', 'A', 'protected'), 'Child'));
        self::assertFalse($lookup->allowed(new \Deriver\Internal\IR\ClassConstant('Base', 'A', 'private'), 'Child'));
        self::assertFalse($lookup->allowed(new \Deriver\Internal\IR\ClassConstant('Base', 'A', 'protected'), ''));
    }
}
