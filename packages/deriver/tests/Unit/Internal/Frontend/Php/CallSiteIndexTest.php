<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\CallSiteIndex
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\CallSiteIndex::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[Small]
final class CallSiteIndexTest extends TestCase
{
    public function testOwnersIndexesNestedClosuresAsSeparateOwners(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target(){return fn()=>sink(1);}');
        $owners = (new \Deriver\Internal\Frontend\Php\CallSiteIndex($index))->owners('sink');
        self::assertCount(1, $owners);
        self::assertStringStartsWith('closure:fixture.php:', $owners[0]);
        self::assertSame(0, $index->graphCount());
    }
    public function testContainsDoesNotAttributeANestedFunctionCallToItsParent(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target(){function nested(){sink(1);}}');
        $source = $index->declarations['target'];
        $pending = [];
        self::assertFalse((new \Deriver\Internal\Frontend\Php\CallSiteIndex($index))->contains($source->node, 'sink', $source, $pending, true));
    }
}
