<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Parsing;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\Parsing\ParseCache;
use Deriver\Source\Parsing\ParsedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Source\Parsing\ParseCache
 */
#[CoversClass(ParseCache::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(ParsedFile::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[Small]
final class ParseCacheTest extends TestCase
{
    public function testReadReusesIdenticalBytesAcrossPathsButInvalidatesChangedSource(): void
    {
        $cache = new ParseCache();
        $profile = new TargetProfile();
        $cache->read(new SourceFile('one.php', '<?php return 1;'), $profile);
        $cache->read(new SourceFile('two.php', '<?php return 1;'), $profile);
        $cache->read(new SourceFile('one.php', '<?php return 2;'), $profile);
        self::assertSame(1, $cache->hits);
        self::assertSame(2, $cache->misses);
    }
    public function testReadEvictsOldEntriesWithoutAffectingTheReceivingTree(): void
    {
        $cache = new ParseCache();
        $cache->records = array_fill_keys(array_map(static fn (int $id): string => 'old:'.$id, range(1, 128)), new ParsedFile([]));
        $tree = $cache->read(new SourceFile('one.php', '<?php return 1;'), new TargetProfile());
        self::assertCount(128, $cache->records);
        self::assertArrayNotHasKey('old:1', $cache->records);
        self::assertCount(1, $tree->nodes);
    }
    public function testReadEnforcesStricterLimitsOnWarmTrees(): void
    {
        $cache = new ParseCache();
        $file = new SourceFile('a.php', '<?php return 1+2+3;');
        $cache->read($file, new TargetProfile());
        $this->expectException(InvalidInputException::class);
        $cache->read($file, new TargetProfile(), new SourceLimits(depth: 2));
    }
    public function testReadEvictsByAggregateRetentionCost(): void
    {
        $cache = new ParseCache();
        $cache->records['old'] = new ParsedFile([]);
        $cache->weights['old'] = 33554432;
        $cache->read(new SourceFile('a.php', '<?php return 1;'), new TargetProfile());
        self::assertArrayNotHasKey('old', $cache->records);
        self::assertLessThanOrEqual(33554432, array_sum($cache->weights));
    }
}
