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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
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

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNamespacedCalls')]
    public function testOwnersFindsResolvedNamespaceAndImportedNamesWithoutCompilingBodies(string $source, string $selector): void
    {
        $index = \Tests\Fake\FrontendFixture::index($source);
        self::assertSame(['N\\target'], (new \Deriver\Internal\Frontend\Php\CallSiteIndex($index))->owners($selector));
        self::assertSame(0, $index->graphCount());
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerNamespacedCalls(): iterable
    {
        yield 'implicit namespace' => ['<?php namespace N;function target(){sink(1);}', 'N\\sink'];
        yield 'case-insensitive qualified selector' => ['<?php namespace N;function target(){sink(1);}', '\\n\\SINK'];
        yield 'qualified syntax' => ['<?php namespace N;function target(){\\N\\sink(1);}', 'N\\sink'];
        yield 'function import' => ['<?php namespace N;use function M\\sink as alias;function target(){alias(1);}', 'M\\sink'];
        yield 'namespace-relative syntax' => ['<?php namespace N;function target(){namespace\\sink(1);}', 'N\\sink'];
        yield 'ordinary method name' => ['<?php namespace N;function target($x){$x->sink(1);}', 'SINK'];
    }
}
