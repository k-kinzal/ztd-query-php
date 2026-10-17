<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Transfer;

use Deriver\Evaluation\Transfer\PropertyLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Transfer\PropertyLookup
 */
#[CoversClass(PropertyLookup::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Builtins::class)]
#[UsesClass(\Deriver\ControlFlow\Hierarchy\Dispatch::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
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
#[Small]
final class PropertyLookupTest extends TestCase
{
    public function testFindPrefersTheLexicalPrivateSlotInASubclass(): void
    {
        $program = \Tests\Fake\SourceFixture::index('<?php class A{private $x;}class B extends A{public $x;}');
        $lookup = new PropertyLookup($program);
        self::assertSame('A', $lookup->find('B', 'A', 'x')?->className);
        self::assertSame('B', $lookup->find('B', '', 'x')?->className);
    }
    public function testAccessibleAllowsProtectedAccessFromRelatedClasses(): void
    {
        $program = \Tests\Fake\SourceFixture::index('<?php class A{protected $x;}class B extends A{}');
        $lookup = new PropertyLookup($program);
        $property = $lookup->find('B', '', 'x');
        self::assertTrue($lookup->accessible($property, 'B'));
        self::assertFalse($lookup->accessible($property, 'Unrelated'));
    }
}
