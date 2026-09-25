<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\Registry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\Unit;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SourceTest extends TestCase
{
    #[DataProvider('formats')]
    public function testSelectsStableDistinctUnits(string $format, string $file, string $selector, int $count): void
    {
        $source = new Source('test', $file, $format, $selector);
        $extension = (new Registry())->get($format);
        $units = $extension->select($source, $selector, __DIR__ . '/../Fixtures', false);
        self::assertCount($count, $units);
        self::assertCount($count, array_unique(array_map(static fn (Unit $unit): string => $unit->location, $units)));
        self::assertSame($units[0]->location, $extension->select($source, $selector, __DIR__ . '/../Fixtures', false)[0]->location);
    }

    /** @return list<array{string, string, string, int}> */
    public static function formats(): array
    {
        return [
            ['html', 'source.html', 'main p', 3],
            ['xml', 'source.xml', 'section t', 2],
            ['ietf', 'source.xml', 'section t', 2],
            ['markdown', 'source.md', 'p', 2],
            ['json', 'source.json', '$.rules[*].text', 3],
            ['json', 'source.json', '$..text', 3],
            ['json', 'source.json', "$['rules'][1]['text']", 1],
            ['text', 'source.md', 'lines:1-5', 3],
        ];
    }

    public function testRejectsNestedCoverageUnits(): void
    {
        $source = new Source('test', 'source.html', 'html', 'main, main p');
        $this->expectException(RuntimeException::class);
        (new Registry())->get('html')->select($source, $source->selector, __DIR__ . '/../Fixtures', false);
    }

    public function testVerifiesSnapshotHash(): void
    {
        $source = new Source('test', 'https://example.invalid/manual', 'html', 'p', 'source.html', str_repeat('0', 64));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA-256');
        (new Registry())->get('html')->select($source, 'p', __DIR__ . '/../Fixtures', false);
    }

    public function testRejectsUnsupportedJsonPathInsteadOfSilentlySelecting(): void
    {
        $source = new Source('test', 'source.json', 'json', '$.rules[?(@.text)]');
        $this->expectException(RuntimeException::class);
        (new Registry())->get('json')->select($source, $source->selector, __DIR__ . '/../Fixtures', false);
    }

    public function testHttpContentIsBoundedAndFailuresAreNotRepeated(): void
    {
        $client = new MockHttpClient([new MockResponse(str_repeat('x', 16777217))]);
        $loader = new ResourceLoader($client);
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1');
        for ($i = 0; $i < 2; ++$i) {
            try {
                $loader->read($source, '.', false);
                self::fail('Expected a bounded download.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('16 MiB', $error->getMessage());
            }
        }
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testHttpErrorCannotBecomeValidEvidence(): void
    {
        $loader = new ResourceLoader(new MockHttpClient(new MockResponse('Names shall start with a letter.', ['http_code' => 404])));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');
        $loader->read(new Source('test', 'https://example.org/missing', 'text', 'lines:1'), '.', false);
    }

    public function testHttpContentIsCachedWithinTheCommand(): void
    {
        $client = new MockHttpClient(new MockResponse('Source text.'));
        $loader = new ResourceLoader($client);
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1');
        self::assertSame('Source text.', $loader->read($source, '.', false));
        self::assertSame('Source text.', $loader->read($source, '.', false));
        self::assertSame(1, $client->getRequestsCount());
    }

}
