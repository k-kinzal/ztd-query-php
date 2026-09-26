<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Transfer\PropertyLookup
 */
#[CoversClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
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
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[Small]
final class PropertyLookupTest extends TestCase
{
    public function testFindPrefersTheLexicalPrivateSlotInASubclass(): void
    {
        $program = \Tests\Fake\FrontendFixture::index('<?php class A{private $x;}class B extends A{public $x;}');
        $lookup = new \Deriver\Internal\Solver\Transfer\PropertyLookup($program);
        self::assertSame('A', $lookup->find('B', 'A', 'x')?->className);
        self::assertSame('B', $lookup->find('B', '', 'x')?->className);
    }
    public function testAccessibleAllowsProtectedAccessFromRelatedClasses(): void
    {
        $program = \Tests\Fake\FrontendFixture::index('<?php class A{protected $x;}class B extends A{}');
        $lookup = new \Deriver\Internal\Solver\Transfer\PropertyLookup($program);
        $property = $lookup->find('B', '', 'x');
        self::assertTrue($lookup->accessible($property, 'B'));
        self::assertFalse($lookup->accessible($property, 'Unrelated'));
    }
}
