<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;

#[CoversClass(Source::class)]
#[UsesClass(Fields::class)]
#[Small]
final class SourceTest extends TestCase
{
    public function testFromReadsTheRequiredFieldsWithDefaults(): void
    {
        $source = Source::from(['id' => 'manual', 'uri' => 'https://example.org/manual.html', 'format' => 'html', 'selector' => 'main p']);
        self::assertSame('manual', $source->id);
        self::assertSame('https://example.org/manual.html', $source->uri);
        self::assertSame('html', $source->format);
        self::assertSame('main p', $source->selector);
        self::assertNull($source->snapshot);
        self::assertNull($source->sha256);
        self::assertSame([], $source->options);
    }

    public function testFromReadsSnapshotDigestAndOptions(): void
    {
        $hash = str_repeat('0a', 32);
        $source = Source::from(['id' => 'notes', 'uri' => 'notes.txt', 'format' => 'text', 'selector' => 'lines:1-3', 'snapshot' => 'cache/notes.txt', 'sha256' => $hash, 'options' => ['encoding' => 'utf-8']]);
        self::assertSame('cache/notes.txt', $source->snapshot);
        self::assertSame($hash, $source->sha256);
        self::assertSame(['encoding' => 'utf-8'], $source->options);
    }

    public function testFromAcceptsADigestWithoutSnapshot(): void
    {
        $hash = str_repeat('f', 64);
        $source = Source::from(['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'p', 'sha256' => $hash]);
        self::assertNull($source->snapshot);
        self::assertSame($hash, $source->sha256);
    }

    public function testFromTreatsNullSnapshotAndDigestAsAbsent(): void
    {
        $source = Source::from(['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'p', 'snapshot' => null, 'sha256' => null]);
        self::assertNull($source->snapshot);
        self::assertNull($source->sha256);
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('providerFromRejectsSnapshotsWithoutAValidDigest')]
    public function testFromRejectsSnapshotsWithoutAValidDigest(array $extra): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('A snapshot requires a lowercase SHA-256 digest.');
        Source::from(['id' => 'manual', 'uri' => 'https://example.org/', 'format' => 'html', 'selector' => 'p', ...$extra]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function providerFromRejectsSnapshotsWithoutAValidDigest(): array
    {
        return [
            'snapshot without digest' => [['snapshot' => 'cache/manual.html']],
            'uppercase digest' => [['snapshot' => 'cache/manual.html', 'sha256' => str_repeat('A', 64)]],
            'short digest' => [['sha256' => str_repeat('a', 63)]],
            'long digest' => [['sha256' => str_repeat('a', 65)]],
            'digest with newline' => [['sha256' => str_repeat('a', 64) . "\n"]],
            'digest with prefix' => [['sha256' => 'x' . str_repeat('a', 64)]],
            'non-hex digest' => [['sha256' => str_repeat('g', 64)]],
        ];
    }

    #[DataProvider('providerFromRejectsInvalidMappings')]
    public function testFromRejectsInvalidMappings(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        Source::from($value);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerFromRejectsInvalidMappings(): array
    {
        $source = ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'p'];
        return [
            'not a mapping' => ['source.html', 'source must be a mapping.'],
            'unknown field' => [[...$source, 'scope' => 'p'], "source: unknown field 'scope'."],
            'missing id' => [['uri' => 'source.html', 'format' => 'html', 'selector' => 'p'], 'id must be a nonempty string.'],
            'missing uri' => [['id' => 'manual', 'format' => 'html', 'selector' => 'p'], 'uri must be a nonempty string.'],
            'missing format' => [['id' => 'manual', 'uri' => 'source.html', 'selector' => 'p'], 'format must be a nonempty string.'],
            'missing selector' => [['id' => 'manual', 'uri' => 'source.html', 'format' => 'html'], 'selector must be a nonempty string.'],
            'blank snapshot' => [[...$source, 'snapshot' => ' '], 'snapshot must be a nonempty string.'],
            'blank digest' => [[...$source, 'sha256' => ''], 'sha256 must be a nonempty string.'],
            'options not a mapping' => [[...$source, 'options' => ['utf-8']], 'source.options must have string keys.'],
            'options scalar' => [[...$source, 'options' => 'utf-8'], 'source.options must be a mapping.'],
        ];
    }
}
