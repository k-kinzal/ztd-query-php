<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\CallSiteIndex;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Declaration\CallSiteIndex
 */
#[CoversClass(CallSiteIndex::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
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
