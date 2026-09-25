<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Loader;
use Requirements\Config\SchemaValidator;
use Requirements\Console\Formatter;
use Requirements\Input\InvalidInputException;
use Tests\Support\Workspace;

final class SchemaTest extends TestCase
{
    public function testBundledSchemaDeclarationsResolveOfflineAndPreserveMaps(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $data = $loader->document($workspace->directory . '/definition.yaml');
        $data = ['$schema' => SchemaValidator::BASE . 'definition.schema.json', ...$data];
        $workspace->write('definition.yaml', $data);
        file_put_contents($workspace->directory . '/definition.yaml', "    metadata: {}\n", FILE_APPEND);
        $workspace->write('requirements.yaml', ['$schema' => SchemaValidator::BASE . 'config.schema.json', 'version' => 1, 'definitions' => ['definition.yaml']]);
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        (new Formatter())->format($project->files, false);
        self::assertCount(1, $loader->load($workspace->directory . '/requirements.yaml')->items);
        $formatted = file_get_contents($workspace->directory . '/definition.yaml');
        self::assertIsString($formatted);
        self::assertStringContainsString('metadata: {  }', $formatted);
    }

    public function testLocalSchemaAddsConstraintsInsteadOfReplacingBuiltinSchema(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/team.schema.json', '{"type":"object","properties":{"items":{"items":{"required":["category"]}}}}');
        $data = (new Loader())->document($workspace->directory . '/definition.yaml');
        $data['$schema'] = './team.schema.json';
        $workspace->write('definition.yaml', $data);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('category');
        (new Loader())->load($workspace->directory . '/requirements.yaml');
    }

    #[DataProvider('invalidDocuments')]
    public function testSchemaRejectsWrongTypesUnknownPropertiesAndDeclarations(string $yaml, string $message): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/definition.yaml', $yaml);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Loader())->load($workspace->directory . '/requirements.yaml');
    }

    /** @return list<array{string, string}> */
    public static function invalidDocuments(): array
    {
        return [
            ["version: 1\nsource: null\nitems: {}\n", '/items'],
            ["version: 1\nsource: []\nitems: []\n", '/source'],
            ["version: 1\nsource: null\nitems: []\nlables: []\n", 'lables'],
            ["\$schema: missing.json\nversion: 1\nsource: null\nitems: []\n", 'cannot read schema'],
            ["\$schema: https://example.org/unknown.json\nversion: 1\nsource: null\nitems: []\n", 'unknown $schema'],
            ["version: '1'\nsource: null\nitems: []\n", '/version'],
        ];
    }
}
