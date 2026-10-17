<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Member;

use Deriver\ControlFlow\ClassConstant;
use Deriver\Evaluation\Call\Member\Constants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Member\Constants
 */
#[CoversClass(Constants::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\ConstantSignatures::class)]
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
final class ConstantsTest extends TestCase
{
    public function testFindRetainsTheDeclaringClassOfAnInheritedExpression(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Base{protected const A=1+2;}class Child extends Base{}');
        $constant = (new Constants($context->program))->find('Child', 'A');
        self::assertSame('Base', $constant?->className);
        self::assertSame('protected', $constant->visibility);
    }
    public function testAllowedUsesTheLexicalClassAndVisibility(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Base{}class Child extends Base{}');
        $lookup = new Constants($context->program);
        self::assertTrue($lookup->allowed(new ClassConstant('Base', 'A', 'protected'), 'Child'));
        self::assertFalse($lookup->allowed(new ClassConstant('Base', 'A', 'private'), 'Child'));
        self::assertFalse($lookup->allowed(new ClassConstant('Base', 'A', 'protected'), ''));
    }
}
