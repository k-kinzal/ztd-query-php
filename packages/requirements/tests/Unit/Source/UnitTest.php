<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\Unit;

#[CoversClass(Unit::class)]
#[UsesClass(Source::class)]
#[Small]
final class UnitTest extends TestCase
{
    public function testKeyHashesTheUriFormatAndLocation(): void
    {
        $unit = new Unit('line:1', 'Names start with a letter.');

        self::assertSame(hash('sha256', "notes.txt\0text\0line:1"), $unit->key(new Source('notes', 'notes.txt', 'text', 'lines:1')));
    }

    public function testKeyIgnoresTheIdSelectorAndText(): void
    {
        $key = (new Unit('line:1', 'First.'))->key(new Source('notes', 'notes.txt', 'text', 'lines:1'));

        self::assertSame($key, (new Unit('line:1', 'Second.'))->key(new Source('other', 'notes.txt', 'text', 'lines:1-9')));
    }

    #[DataProvider('providerKeyDistinguishes')]
    public function testKeyDistinguishesUnits(string $uri, string $format, string $location): void
    {
        $key = (new Unit('line:1', 'Text.'))->key(new Source('notes', 'notes.txt', 'text', 'lines:1'));

        self::assertNotSame($key, (new Unit($location, 'Text.'))->key(new Source('notes', $uri, $format, 'lines:1')));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerKeyDistinguishes(): array
    {
        return [
            'uri' => ['other.txt', 'text', 'line:1'],
            'format' => ['notes.txt', 'markdown', 'line:1'],
            'location' => ['notes.txt', 'text', 'line:2'],
            'boundary' => ['notes.txtte', 'xt', 'line:1'],
        ];
    }

    #[DataProvider('providerNormalize')]
    public function testNormalize(string $text, string $expected): void
    {
        self::assertSame($expected, Unit::normalize($text));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerNormalize(): array
    {
        return [
            'collapsed whitespace' => ["  Names\n\tstart  with a letter. ", 'Names start with a letter.'],
            'no-break space' => ["Names\u{00a0}\u{00a0}start.", 'Names start.'],
            'carriage return' => ["Names\r\nstart.", 'Names start.'],
            'blank' => [" \n\t ", ''],
            'unicode kept' => ['日本語 text', '日本語 text'],
            'invalid UTF-8 only trimmed' => ["  \xFF  text ", "\xFF  text"],
        ];
    }
}
