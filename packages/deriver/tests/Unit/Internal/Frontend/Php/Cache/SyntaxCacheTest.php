<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Frontend\Php\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Frontend\Php\Cache\SyntaxCache
 */
#[CoversClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[Small]
final class SyntaxCacheTest extends TestCase
{
    public function testReadReusesIdenticalBytesAcrossPathsButInvalidatesChangedSource(): void
    {
        $cache = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache();
        $profile = new \Deriver\Api\Project\TargetProfile();
        $cache->read(new \Deriver\Api\Project\SourceFile('one.php', '<?php return 1;'), $profile);
        $cache->read(new \Deriver\Api\Project\SourceFile('two.php', '<?php return 1;'), $profile);
        $cache->read(new \Deriver\Api\Project\SourceFile('one.php', '<?php return 2;'), $profile);
        self::assertSame(1, $cache->hits);
        self::assertSame(2, $cache->misses);
    }
    public function testReadEvictsOldEntriesWithoutAffectingTheReceivingTree(): void
    {
        $cache = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache();
        $cache->records = array_fill_keys(array_map(static fn (int $id): string => 'old:'.$id, range(1, 128)), new \Deriver\Internal\Frontend\Php\Cache\SyntaxTree([]));
        $tree = $cache->read(new \Deriver\Api\Project\SourceFile('one.php', '<?php return 1;'), new \Deriver\Api\Project\TargetProfile());
        self::assertCount(128, $cache->records);
        self::assertArrayNotHasKey('old:1', $cache->records);
        self::assertCount(1, $tree->nodes);
    }
    public function testParseCapturesMalformedSourceWithoutExecutingIt(): void
    {
        $tree = (new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache())->parse('<?php function {', new \Deriver\Api\Project\TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
        self::assertSame(1, $tree->errors[0]['line']);
    }
    public function testParseDoesNotExportNewerSyntaxIntoTheTargetWorld(): void
    {
        $cache = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache();
        $tree = $cache->parse('<?php function target(){r(?);}', new \Deriver\Api\Project\TargetProfile());
        self::assertSame([], $tree->nodes);
        self::assertNotEmpty($tree->errors);
    }
    public function testReadEnforcesStricterLimitsOnWarmTrees(): void
    {
        $cache = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache();
        $file = new \Deriver\Api\Project\SourceFile('a.php', '<?php return 1+2+3;');
        $cache->read($file, new \Deriver\Api\Project\TargetProfile());
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $cache->read($file, new \Deriver\Api\Project\TargetProfile(), new \Deriver\Api\Execution\SourceLimits(depth: 2));
    }
    public function testReadEvictsByAggregateRetentionCost(): void
    {
        $cache = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache();
        $cache->records['old'] = new \Deriver\Internal\Frontend\Php\Cache\SyntaxTree([]);
        $cache->weights['old'] = 33554432;
        $cache->read(new \Deriver\Api\Project\SourceFile('a.php', '<?php return 1;'), new \Deriver\Api\Project\TargetProfile());
        self::assertArrayNotHasKey('old', $cache->records);
        self::assertLessThanOrEqual(33554432, array_sum($cache->weights));
    }
    public function testParseRejectsDeepChainsWithoutRecursiveVisitorFailure(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache())->parse('<?php return '.str_repeat('1+', 1000).'1;', new \Deriver\Api\Project\TargetProfile());
    }
    public function testParseRejectsOversizedBytesBeforeTokenization(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache())->parse('<?php return 1;', new \Deriver\Api\Project\TargetProfile(), new \Deriver\Api\Execution\SourceLimits(fileBytes: 2));
    }
}
