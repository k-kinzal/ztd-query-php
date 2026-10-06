<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Cache;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Cache\SyntaxCache
 */
#[CoversClass(SyntaxCache::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[Small]
final class SyntaxCacheTest extends TestCase
{
    public function testReadReusesIdenticalBytesAcrossPathsButInvalidatesChangedSource(): void
    {
        $cache = new SyntaxCache();
        $profile = new TargetProfile();
        $cache->read(new SourceFile('one.php', '<?php return 1;'), $profile);
        $cache->read(new SourceFile('two.php', '<?php return 1;'), $profile);
        $cache->read(new SourceFile('one.php', '<?php return 2;'), $profile);
        self::assertSame(1, $cache->hits);
        self::assertSame(2, $cache->misses);
    }
    public function testReadEvictsOldEntriesWithoutAffectingTheReceivingTree(): void
    {
        $cache = new SyntaxCache();
        $cache->records = array_fill_keys(array_map(static fn (int $id): string => 'old:'.$id, range(1, 128)), new SyntaxTree([]));
        $tree = $cache->read(new SourceFile('one.php', '<?php return 1;'), new TargetProfile());
        self::assertCount(128, $cache->records);
        self::assertArrayNotHasKey('old:1', $cache->records);
        self::assertCount(1, $tree->nodes);
    }
    public function testParseCapturesMalformedSourceWithoutExecutingIt(): void
    {
        $tree = (new SyntaxCache())->parse('<?php function {', new TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
        self::assertSame(1, $tree->errors[0]['line']);
    }
    public function testParseDoesNotExportNewerSyntaxIntoTheTargetWorld(): void
    {
        $cache = new SyntaxCache();
        $tree = $cache->parse('<?php function target(){r(?);}', new TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
    }
    public function testReadEnforcesStricterLimitsOnWarmTrees(): void
    {
        $cache = new SyntaxCache();
        $file = new SourceFile('a.php', '<?php return 1+2+3;');
        $cache->read($file, new TargetProfile());
        $this->expectException(InvalidInputException::class);
        $cache->read($file, new TargetProfile(), new SourceLimits(depth: 2));
    }
    public function testReadEvictsByAggregateRetentionCost(): void
    {
        $cache = new SyntaxCache();
        $cache->records['old'] = new SyntaxTree([]);
        $cache->weights['old'] = 33554432;
        $cache->read(new SourceFile('a.php', '<?php return 1;'), new TargetProfile());
        self::assertArrayNotHasKey('old', $cache->records);
        self::assertLessThanOrEqual(33554432, array_sum($cache->weights));
    }
    public function testParseRejectsDeepChainsWithoutRecursiveVisitorFailure(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SyntaxCache())->parse('<?php return '.str_repeat('1+', 1000).'1;', new TargetProfile());
    }
    public function testParseRejectsOversizedBytesBeforeTokenization(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SyntaxCache())->parse('<?php return 1;', new TargetProfile(), new SourceLimits(fileBytes: 2));
    }
}
