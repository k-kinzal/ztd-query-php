<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\DomSource;
use Requirements\Source\LocalFile;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;
use RuntimeException;
use Tests\Fake\ProjectDirectory;
use Tests\Fake\SourceDocuments;
use Tests\Fake\SourceFailure;

#[CoversClass(DomSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class DomSourceTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectStableDistinctUnits')]
    public function testSelectStableDistinctUnits(string $format, string $file, string $document, string $selector, int $count): void
    {
        $project = new ProjectDirectory();
        $project->put($file, $document);
        $source = new Source('test', $file, $format, $selector);
        $extension = new DomSource();
        $units = $extension->select($source, $selector, $project->directory, false);

        self::assertCount($count, $units);
        self::assertCount($count, array_unique(array_map(static fn (Unit $unit): string => $unit->location, $units)));
        self::assertSame($units[0]->location, $extension->select($source, $selector, $project->directory, false)[0]->location);
    }

    /**
     * @return array<string, array{string, string, string, string, int}>
     */
    public static function providerSelectStableDistinctUnits(): array
    {
        return [
            'html' => ['html', 'source.html', SourceDocuments::HTML, 'main p', 3],
            'xml' => ['xml', 'source.xml', SourceDocuments::XML, 'section t', 2],
            'ietf' => ['ietf', 'source.xml', SourceDocuments::XML, 'section t', 2],
            'markdown' => ['markdown', 'source.md', SourceDocuments::MARKDOWN, 'p', 2],
        ];
    }

    /**
     * @param list<Unit> $expected
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectUnits')]
    public function testSelectLocatesUnitsByNodePathWithNormalizedText(string $format, string $file, string $document, string $selector, array $expected): void
    {
        $project = new ProjectDirectory();
        $project->put($file, $document);

        self::assertEquals($expected, (new DomSource())->select(new Source('test', $file, $format, $selector), $selector, $project->directory, false));
    }

    /**
     * @return array<string, array{string, string, string, string, list<Unit>}>
     */
    public static function providerSelectUnits(): array
    {
        return [
            'html' => ['html', 'source.html', SourceDocuments::HTML, 'main p', [
                new Unit('/html/body/main/p[1]', 'Names shall start with a letter.'),
                new Unit('/html/body/main/p[2]', 'Names may contain digits.'),
                new Unit('/html/body/main/p[3]', 'The generator shall produce C code.'),
            ]],
            'xml' => ['xml', 'source.xml', SourceDocuments::XML, 'section t', [
                new Unit('/rfc/section/t[1]', 'First rule.'),
                new Unit('/rfc/section/t[2]', 'Second rule.'),
            ]],
            'ietf' => ['ietf', 'source.xml', SourceDocuments::XML, 'section[anchor="rules"] t', [
                new Unit('/rfc/section/t[1]', 'First rule.'),
                new Unit('/rfc/section/t[2]', 'Second rule.'),
            ]],
            'markdown' => ['markdown', 'source.md', SourceDocuments::MARKDOWN, 'p', [
                new Unit('/html/body/p[1]', 'First rule.'),
                new Unit('/html/body/p[2]', 'Second rule.'),
            ]],
            'markdown heading' => ['markdown', 'source.md', SourceDocuments::MARKDOWN, 'h1', [
                new Unit('/html/body/h1', 'Rules'),
            ]],
            'whitespace' => ['html', 'spaced.html', "<main><p>  Names\n\tshall\u{00a0}start. </p></main>", 'p', [
                new Unit('/html/body/main/p', 'Names shall start.'),
            ]],
            'nothing selected' => ['html', 'source.html', SourceDocuments::HTML, 'table', []],
            'html is not read as xml' => ['html', 'tags.html', '<main><P>Upper.</P></main>', 'p', [
                new Unit('/html/body/main/p', 'Upper.'),
            ]],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectMarkdownIsRenderedSafely')]
    public function testSelectMarkdownIsRenderedSafely(string $selector): void
    {
        $project = new ProjectDirectory();
        $project->put('unsafe.md', "<script>alert(1)</script>\n\n[Link](javascript:alert(1))\n");

        self::assertSame([], (new DomSource())->select(new Source('test', 'unsafe.md', 'markdown', $selector), $selector, $project->directory, false));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectMarkdownIsRenderedSafely(): array
    {
        return [
            'raw html stripped' => ['script'],
            'unsafe link removed' => ['a[href]'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectRejectsNestedCoverageUnits(): void
    {
        $source = new Source('test', 'source.html', 'html', 'main, main p');
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A source selection cannot contain both ancestor and descendant units. Select atomic elements.');
        (new DomSource())->select($source, $source->selector, $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectAcceptsSiblingsSharingALocationPrefix(): void
    {
        $project = new ProjectDirectory();
        $project->put('prefix.html', '<main><p>Rule.</p><pre>Code.</pre></main>');

        self::assertEquals(
            [new Unit('/html/body/main/p', 'Rule.'), new Unit('/html/body/main/pre', 'Code.')],
            (new DomSource())->select(new Source('test', 'prefix.html', 'html', 'p, pre'), 'p, pre', $project->directory, false),
        );
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectAcceptsSiblingsSharingAnIndexPrefix(): void
    {
        $project = new ProjectDirectory();
        $project->put('many.html', '<main>' . str_repeat('<p>Rule.</p>', 10) . '</main>');
        $units = (new DomSource())->select(new Source('test', 'many.html', 'html', 'p'), 'p', $project->directory, false);

        self::assertCount(10, $units);
        self::assertSame('/html/body/main/p[10]', $units[9]->location);
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectVerifiesSnapshotHash(): void
    {
        $source = new Source('test', 'https://example.invalid/manual', 'html', 'p', 'source.html', str_repeat('0', 64));
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA-256');
        (new DomSource())->select($source, 'p', $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectReadsThroughTheGivenLoader(): void
    {
        $project = new ProjectDirectory();
        $loader = new ResourceLoader();
        $loader->fetch($project->path('source.html'));
        $project->put('source.html', '<main><p>Changed.</p></main>');

        self::assertCount(3, (new DomSource($loader))->select(new Source('test', 'source.html', 'html', 'main p'), 'main p', $project->directory, false));
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectExactDirectiveLocatesTheSameUnitAsCssWithoutChangingTheScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $extension = new DomSource();
        $project = new ProjectDirectory();
        $directory = $project->directory;
        $css = $extension->select($source, '#a', $directory, false);
        $fragment = $extension->select($source, '#:~:text=Names%20shall%20start%20with%20a%20letter.', $directory, false);

        self::assertEquals($css, $fragment);
        self::assertCount(3, $extension->select($source, $source->selector, $directory, false));
        self::assertSame([], $extension->select($source, '#:~:text=Names%20shall', $directory, false));
        self::assertSame([], $extension->select(new Source('manual', 'source.html', 'html', '#b'), '#:~:text=Names%20shall%20start%20with%20a%20letter.', $directory, false));
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectDirectiveSelectsEveryUnitWithTheText(): void
    {
        $project = new ProjectDirectory();
        $project->put('repeated.html', '<main><p>Repeated text.</p><p>Other.</p><p> Repeated  text. </p></main>');

        self::assertEquals(
            [new Unit('/html/body/main/p[1]', 'Repeated text.'), new Unit('/html/body/main/p[3]', 'Repeated text.')],
            (new DomSource())->select(new Source('manual', 'repeated.html', 'html', 'main p'), '#:~:text=Repeated%20text.', $project->directory, false),
        );
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectDirectiveStillRejectsNestedScopes(): void
    {
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ancestor and descendant');
        (new DomSource())->select(new Source('manual', 'source.html', 'html', 'main, main p'), '#:~:text=Names%20shall%20start%20with%20a%20letter.', $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectTextDirectivesCannotReplaceTheCoverageScope(): void
    {
        $source = new Source('manual', 'source.html', 'html', '#:~:text=Names');
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('coverage scope');
        (new DomSource())->select($source, $source->selector, $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectDirectiveOutsideHtml')]
    public function testSelectRejectsDirectivesOutsideHtml(string $format): void
    {
        $project = new ProjectDirectory();
        $project->put('source.xml', SourceDocuments::XML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Text Fragments select evidence in an HTML CSS scope; they cannot define the coverage scope.');
        (new DomSource())->select(new Source('manual', 'source.xml', $format, 'section t'), '#:~:text=First%20rule.', $project->directory, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectDirectiveOutsideHtml(): array
    {
        return [
            'xml' => ['xml'],
            'ietf' => ['ietf'],
            'markdown' => ['markdown'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectRejectsDirectivesBeforeReadingTheSource(): void
    {
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('coverage scope');
        (new DomSource())->select(new Source('manual', 'missing.xml', 'xml', 't'), '#:~:text=Rule.', $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectXmlEntitiesAreRejected(): void
    {
        $project = new ProjectDirectory();
        $project->put('unsafe.xml', '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><x>&secret;</x>');
        $source = new Source('test', 'unsafe.xml', 'xml', 'x');

        $this->expectException(RuntimeException::class);
        (new DomSource())->select($source, 'x', $project->directory, false);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectUnsafeXml')]
    public function testSelectRejectsDtdAndEntityDeclarations(string $format, string $document): void
    {
        $project = new ProjectDirectory();
        $project->put('unsafe.xml', $document);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XML sources cannot contain DTD or entity declarations.');
        (new DomSource())->select(new Source('test', 'unsafe.xml', $format, 'x'), 'x', $project->directory, false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerSelectUnsafeXml(): array
    {
        return [
            'entity' => ['xml', '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><x>&secret;</x>'],
            'lowercase doctype' => ['xml', '<!doctype x><x>Text.</x>'],
            'entity without doctype' => ['ietf', '<x><!entity a "b"></x>'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectAcceptsHtmlDoctype(): void
    {
        $project = new ProjectDirectory();

        self::assertCount(1, (new DomSource())->select(new Source('test', 'source.html', 'html', 'aside p'), 'aside p', $project->directory, false));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerSelectMalformedXml')]
    public function testSelectRejectsMalformedXml(string $format): void
    {
        $project = new ProjectDirectory();
        $project->put('broken.xml', '<rfc><t>Unclosed.</rfc>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed XML source.');
        (new DomSource())->select(new Source('test', 'broken.xml', $format, 't'), 't', $project->directory, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectMalformedXml(): array
    {
        return [
            'xml' => ['xml'],
            'ietf' => ['ietf'],
        ];
    }

    public function testSelectLeavesNoXmlErrorsBehind(): void
    {
        $project = new ProjectDirectory();
        $project->put('broken.xml', '<rfc><t>Unclosed.</rfc>');

        self::assertSame('Malformed XML source.', SourceFailure::message(static fn (): array => (new DomSource())->select(new Source('test', 'broken.xml', 'xml', 't'), 't', $project->directory, false)));
        self::assertSame([], libxml_get_errors());
        self::assertFalse(libxml_use_internal_errors(false));
    }

    /**
     * @throws CommonMarkException
     */
    public function testSelectRestoresInternalXmlErrorHandling(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.xml', SourceDocuments::XML);
        (new DomSource())->select(new Source('test', 'source.xml', 'xml', 't'), 't', $project->directory, false);

        self::assertFalse(libxml_use_internal_errors(false));
    }
}
