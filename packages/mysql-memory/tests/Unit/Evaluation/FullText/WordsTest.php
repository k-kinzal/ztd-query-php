<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Words::class)]
#[Small]
final class WordsTest extends TestCase
{
    public function testForEngineUsesDistinctMinimumLengthsAndStopwordDictionaries(): void
    {
        $collation = Collation::known('utf8mb4_bin');
        self::assertFalse(Words::forEngine($collation, true)->indexed('another'));
        self::assertTrue(Words::forEngine($collation, false)->indexed('another'));
        self::assertFalse(Words::forEngine($collation, true)->indexed('cat'));
        self::assertTrue(Words::forEngine($collation, false)->indexed('cat'));
        self::assertCount(543, Words::MYISAM_STOPWORDS);
    }

    public function testSplitPreservesInternalSingleApostrophes(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame(["aaa'bbb", 'aaa', 'bbb', 'words_123', '日本語'], $words->split("'aaa'bbb' aaa''bbb words_123 日本語", Charset::known('utf8mb4')));
    }

    public function testIndexedAppliesBothLengthBoundsAndStopwords(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertFalse($words->indexed('the'));
        self::assertFalse($words->indexed('ab'));
        self::assertFalse($words->indexed(str_repeat('x', 85)));
        self::assertTrue($words->indexed(str_repeat('x', 84)));
        self::assertTrue($words->indexed('日本語'));
    }

    public function testKeyUsesTheColumnCollation(): void
    {
        $words = new Words(Collation::known('utf8mb4_0900_ai_ci'));
        self::assertSame($words->key('cafe'), $words->key('CAFÉ'));
        self::assertNotSame((new Words(Collation::known('utf8mb4_bin')))->key('cafe'), (new Words(Collation::known('utf8mb4_bin')))->key('CAFÉ'));
    }

    public function testFrequenciesRetainsTermCountsWithoutStopwords(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame([$words->key('apple') => 2, $words->key('pear') => 1], $words->frequencies(['apple', 'apple', 'the', 'pear']));
    }
}
