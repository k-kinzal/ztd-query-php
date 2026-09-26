<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Frontmatter;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Symfony\Component\Yaml\Exception\ParseException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Frontmatter::class)]
#[UsesClass(Fields::class)]
#[Small]
final class FrontmatterTest extends TestCase
{
    public function testReadSplitsTheFrontmatterFromTheBody(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\nThe reader shall emit a tree.\n");
        [$data, $body] = (new Frontmatter())->read($file);
        self::assertEquals((object) ['version' => 1, 'source' => null], $data);
        self::assertSame("\n# SPEC-001\n\nThe reader shall emit a tree.\n", $body);
    }

    public function testReadAcceptsCarriageReturnLineFeeds(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\r\nversion: 1\r\n---\r\n# SPEC-001\r\n");
        [$data, $body] = (new Frontmatter())->read($file);
        self::assertEquals((object) ['version' => 1], $data);
        self::assertSame("# SPEC-001\r\n", $body);
    }

    public function testReadReadsTheSourceAsAMapping(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\n\$schema: definition.document.yaml\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n---\n");
        [$data, $body] = (new Frontmatter())->read($file);
        self::assertEquals((object) ['$schema' => 'definition.document.yaml', 'version' => 1, 'source' => (object) ['id' => 'manual', 'uri' => 'source.html']], $data);
        self::assertSame('', $body);
    }

    #[DataProvider('providerWithoutFrontmatter')]
    public function testReadRejectsAFileWithoutFrontmatter(string $text): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', $text);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$file: expected YAML frontmatter delimited by ---.");
        (new Frontmatter())->read($file);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerWithoutFrontmatter(): array
    {
        return [
            'no frontmatter' => ["# SPEC-001\n\nThe reader shall emit a tree.\n"],
            'unclosed frontmatter' => ["---\nversion: 1\n# SPEC-001\n"],
            'closing delimiter without line break' => ["---\nversion: 1\n---"],
            'text before the frontmatter' => ["\n---\nversion: 1\n---\n"],
        ];
    }

    #[DataProvider('providerNotMappings')]
    public function testReadRejectsFrontmatterThatIsNotAMapping(string $yaml): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\n$yaml\n---\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$file: frontmatter must be a mapping.");
        (new Frontmatter())->read($file);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotMappings(): array
    {
        return [
            'sequence' => ['- version'],
            'scalar' => ['version'],
        ];
    }

    public function testReadRejectsUnknownFrontmatterKeys(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nitems: []\n---\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$file frontmatter: unknown field 'items'.");
        (new Frontmatter())->read($file);
    }

    public function testReadRejectsMalformedYaml(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: [1\n---\n");
        $this->expectException(ParseException::class);
        (new Frontmatter())->read($file);
    }
}
