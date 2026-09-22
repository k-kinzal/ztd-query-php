<?php

declare(strict_types=1);

namespace Requirements\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Loader;
use Requirements\Model\Source;
use Requirements\Report\Analyzer;
use Requirements\Source\Registry;
use Requirements\Source\TextFragment;
use Requirements\Tests\Support\Workspace;
use RuntimeException;

final class TextFragmentTest extends TestCase
{
    public function testDirectiveEncodingPreservesSyntaxCharactersAndUnicode(): void
    {
        $text = 'UTF-8, A&B + "日本語"';
        $fragment = TextFragment::create($text);
        self::assertSame('#:~:text=UTF%2D8%2C%20A%26B%20%2B%20%22%E6%97%A5%E6%9C%AC%E8%AA%9E%22', $fragment);
        self::assertSame($text, TextFragment::text($fragment));
        self::assertSame($text, TextFragment::text(str_replace('#:', '#example:', $fragment)));
    }

    public function testAnExactDirectiveLocatesTheSameUnitAsCssWithoutChangingTheScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $extension = (new Registry())->get('html');
        $directory = __DIR__ . '/../Fixtures';
        $css = $extension->select($source, '#a', $directory, false);
        $fragment = $extension->select($source, '#:~:text=Names%20shall%20start%20with%20a%20letter.', $directory, false);
        self::assertEquals($css, $fragment);
        self::assertCount(3, $extension->select($source, $source->selector, $directory, false));
        self::assertSame([], $extension->select($source, '#:~:text=Names%20shall', $directory, false));
        self::assertSame([], $extension->select(new Source('manual', 'source.html', 'html', '#b'), '#:~:text=Names%20shall%20start%20with%20a%20letter.', $directory, false));
    }

    public function testDuplicateQuotedUnitsFailVerificationAndDoNotCountAsCovered(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/source.html', '<main><p>Repeated text.</p><p>Repeated text.</p></main>');
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [['id' => 'SPEC-001', 'statement' => 'The reader shall preserve text.', 'evidence' => [['selector' => '#:~:text=Repeated%20text.', 'quote' => 'Repeated text.']]]]]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertStringContainsString('exactly one unit', implode(' ', $analysis->errors));
        self::assertSame(2, $analysis->summary()['total']);
        self::assertSame(0, $analysis->summary()['accounted']);
    }

    public function testTextDirectivesCannotReplaceTheCoverageScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', '#:~:text=Names');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('coverage scope');
        (new Registry())->get('html')->select($source, $source->selector, __DIR__ . '/../Fixtures', false);
    }

    #[DataProvider('unsupportedDirectives')]
    public function testUnsupportedOrMalformedDirectivesFailExplicitly(string $fragment): void
    {
        $this->expectException(InvalidArgumentException::class);
        TextFragment::text($fragment);
    }

    /** @return list<array{string}> */
    public static function unsupportedDirectives(): array
    {
        return [
            ['#:~:text='],
            ['#:~:text=start,end'],
            ['#:~:text=prefix-,start'],
            ['#:~:text=start,-suffix'],
            ['#:~:text=first&text=second'],
            ['#:~:text=bad%xx'],
            ['#:~:text=%FF'],
            ['#:~:text=%20'],
            ['#text=missing-directive-marker'],
        ];
    }
}
