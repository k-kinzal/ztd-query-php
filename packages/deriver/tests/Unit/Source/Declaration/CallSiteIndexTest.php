<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Source\Declaration\CallSiteIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Declaration\CallSiteIndex
 */
#[CoversClass(CallSiteIndex::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[Small]
final class CallSiteIndexTest extends TestCase
{
    public function testOwnersIndexesNestedClosuresAsSeparateOwners(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){return fn()=>sink(1);}');
        $owners = (new CallSiteIndex($index))->owners('sink');
        self::assertCount(1, $owners);
        self::assertStringStartsWith('closure:fixture.php:', $owners[0]);
        self::assertSame(0, $index->graphCount());
    }
    public function testContainsDoesNotAttributeANestedFunctionCallToItsParent(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){function nested(){sink(1);}}');
        $source = $index->declarations['target'];
        $pending = [];
        self::assertFalse((new CallSiteIndex($index))->contains($source->node, 'sink', $source, $pending, true));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNamespacedCalls')]
    public function testOwnersFindsResolvedNamespaceAndImportedNamesWithoutCompilingBodies(string $source, string $selector): void
    {
        $index = \Tests\Fake\SourceFixture::index($source);
        self::assertSame(['N\\target'], (new CallSiteIndex($index))->owners($selector));
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
