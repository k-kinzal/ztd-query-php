<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Sources;

use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[Small]
final class SourcesTest extends TestCase
{
    public function testOffsetGetRetainsSourceIdentityAndCallerOrderWithoutReadingFiles(): void
    {
        $first = new Source('z.php', '<?php throw new RuntimeException("must not execute");');
        $second = new Source('a.php', '<?php', true);
        $sources = new Sources([$first, $second]);
        self::assertCount(2, $sources);
        self::assertSame([$first, $second], iterator_to_array($sources));
        self::assertTrue(isset($sources[0]));
        self::assertSame($first, $sources[0]);
        self::assertSame($second, $sources[1]);
        self::assertArrayNotHasKey('sources', get_object_vars($sources));
    }

    public function testCountSupportsAnEmptyCollection(): void
    {
        $sources = new Sources([]);
        self::assertCount(0, $sources);
        self::assertSame([], iterator_to_array($sources));
        self::assertFalse(isset($sources[0]));
        self::assertNull($sources[0] ?? null);
        $this->expectException(OutOfBoundsException::class);
        $sources[0];
    }

    /**
     * @param list<Source> $sources Captured source descriptions
     */
    #[DataProvider('providerInvalidSourcePaths')]
    public function testRejectsEmptyAndRepeatedNormalizedSourceIdentities(array $sources): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source paths must be nonempty and unique:');
        new Sources($sources);
    }

    /**
     * @return iterable<string, array{list<Source>}>
     */
    public static function providerInvalidSourcePaths(): iterable
    {
        yield 'empty' => [[new Source('', '')]];
        yield 'normalized empty' => [[new Source('./', '')]];
        yield 'repeated' => [[new Source('src/a.php', 'first'), new Source('src/a.php', 'second')]];
        yield 'separator alias' => [[new Source('src/a.php', 'first'), new Source('src\\a.php', 'second')]];
        yield 'dot alias' => [[new Source('src/a.php', 'first'), new Source('src/old/../a.php', 'second')]];
    }

    #[DataProvider('providerMissingOffsets')]
    public function testOffsetExistsRejectsMissingAndNonIntegerOffsets(int|string|null $offset): void
    {
        $sources = new Sources([new Source('a.php', '')]);
        self::assertFalse($sources->offsetExists($offset));
        $this->expectException(OutOfBoundsException::class);
        $sources->offsetGet($offset);
    }

    /**
     * @return iterable<string, array{int|string|null}>
     */
    public static function providerMissingOffsets(): iterable
    {
        yield 'negative' => [-1];
        yield 'past the end' => [1];
        yield 'numeric string' => ['0'];
        yield 'string' => ['missing'];
        yield 'null' => [null];
    }

    public function testOffsetSetRejectsReplacement(): void
    {
        $source = new Source('a.php', '');
        $sources = new Sources([$source]);
        $this->expectException(RuntimeException::class);
        try {
            $sources[0] = new Source('b.php', '');
        } finally {
            self::assertCount(1, $sources);
            self::assertSame($source, $sources[0]);
        }
    }

    public function testOffsetSetRejectsAppending(): void
    {
        $sources = new Sources([]);
        $this->expectException(RuntimeException::class);
        try {
            $sources[] = new Source('a.php', '');
        } finally {
            self::assertCount(0, $sources);
        }
    }

    public function testOffsetUnsetRejectsRemoval(): void
    {
        $source = new Source('a.php', '');
        $sources = new Sources([$source]);
        $this->expectException(RuntimeException::class);
        try {
            unset($sources[0]);
        } finally {
            self::assertCount(1, $sources);
            self::assertSame($source, $sources[0]);
        }
    }

    public function testGetIteratorCannotChangeTheCollection(): void
    {
        $source = new Source('a.php', '');
        $sources = new Sources([$source]);
        $iterator = $sources->getIterator();
        unset($iterator[0]);
        self::assertSame($source, $sources[0]);
        self::assertCount(1, $sources);
    }
}
