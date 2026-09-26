<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DefinitionReader;
use Requirements\Config\DocumentReader;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\Loader;
use Requirements\Config\MarkdownDocument;
use Requirements\Config\SchemaValidator;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;
use Tests\Fake\ProjectDirectory;

#[CoversClass(SchemaValidator::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(Fields::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(Loader::class)]
#[UsesClass(MarkdownDocument::class)]
#[Small]
final class SchemaValidatorTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testValidateAddsLocalSchemaConstraintsToBundledSchema(): void
    {
        $project = new ProjectDirectory();
        file_put_contents($project->directory . '/team.schema.json', '{"type":"object","properties":{"items":{"items":{"required":["category"]}}}}');
        $data = (new Loader())->document($project->directory . '/definition.yaml');
        $data['$schema'] = './team.schema.json';
        $project->write('definition.yaml', $data);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('category');
        (new Loader())->load($project->directory . '/requirements.yaml');
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvalidDocuments')]
    public function testValidateRejectsWrongTypesUnknownPropertiesAndDeclarations(string $yaml, string $message): void
    {
        $project = new ProjectDirectory();
        file_put_contents($project->directory . '/definition.yaml', $yaml);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Loader())->load($project->directory . '/requirements.yaml');
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerInvalidDocuments(): array
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

    /**
     * @throws JsonException
     */
    #[DataProvider('providerBundledDeclarations')]
    public function testValidateAcceptsBundledSchemaUri(string $kind, string $file, stdClass $data): void
    {
        $project = new ProjectDirectory();
        (new SchemaValidator())->validate($data, $kind, $project->path($file));
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, string, stdClass}>
     */
    public static function providerBundledDeclarations(): array
    {
        return [
            'definition schema' => ['definition', 'definition.yaml', (object) ['$schema' => SchemaValidator::BASE . 'definition.schema.json', 'version' => 1, 'source' => null, 'items' => []]],
            'definition schema in Markdown' => ['definition', 'definition.md', (object) ['$schema' => SchemaValidator::BASE . 'definition.schema.json', 'version' => 1, 'source' => null, 'items' => []]],
            'document profile in Markdown' => ['definition', 'definition.md', (object) ['$schema' => SchemaValidator::BASE . 'definition.document.yaml', 'version' => 1, 'source' => null, 'items' => []]],
            'document profile in upper case Markdown' => ['definition', 'definition.MARKDOWN', (object) ['$schema' => SchemaValidator::BASE . 'definition.document.yaml', 'version' => 1, 'source' => null, 'items' => []]],
            'configuration schema' => ['config', 'requirements.yaml', (object) ['$schema' => SchemaValidator::BASE . 'config.schema.json', 'version' => 1, 'definitions' => ['definition.yaml']]],
        ];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerUnknownDeclarations')]
    public function testValidateRejectsUnknownRemoteSchema(string $kind, string $file, stdClass $data, string $declared): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path($file) . ": unknown \$schema '$declared'; use the bundled schema URI or a local JSON Schema path.");
        (new SchemaValidator())->validate($data, $kind, $project->path($file));
    }

    /**
     * @return array<string, array{string, string, stdClass, string}>
     */
    public static function providerUnknownDeclarations(): array
    {
        return [
            'other host' => ['definition', 'definition.yaml', (object) ['$schema' => 'https://example.org/unknown.json', 'version' => 1, 'source' => null, 'items' => []], 'https://example.org/unknown.json'],
            'other scheme' => ['definition', 'definition.yaml', (object) ['$schema' => 'ftp://example.org/definition.schema.json', 'version' => 1, 'source' => null, 'items' => []], 'ftp://example.org/definition.schema.json'],
            'schema of the other kind' => ['definition', 'definition.yaml', (object) ['$schema' => SchemaValidator::BASE . 'config.schema.json', 'version' => 1, 'source' => null, 'items' => []], SchemaValidator::BASE . 'config.schema.json'],
            'configuration declaring the definition schema' => ['config', 'requirements.yaml', (object) ['$schema' => SchemaValidator::BASE . 'definition.schema.json', 'version' => 1, 'definitions' => ['definition.yaml']], SchemaValidator::BASE . 'definition.schema.json'],
            'document profile in YAML' => ['definition', 'definition.yaml', (object) ['$schema' => SchemaValidator::BASE . 'definition.document.yaml', 'version' => 1, 'source' => null, 'items' => []], SchemaValidator::BASE . 'definition.document.yaml'],
            'document profile for configuration' => ['config', 'requirements.md', (object) ['$schema' => SchemaValidator::BASE . 'definition.document.yaml', 'version' => 1, 'definitions' => ['definition.yaml']], SchemaValidator::BASE . 'definition.document.yaml'],
            'bundled schema with a prefix' => ['definition', 'definition.yaml', (object) ['$schema' => 'x' . SchemaValidator::BASE . 'definition.schema.json', 'version' => 1, 'source' => null, 'items' => []], 'x' . SchemaValidator::BASE . 'definition.schema.json'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testValidateAcceptsDocumentWithoutDeclaration(): void
    {
        $project = new ProjectDirectory();
        (new SchemaValidator())->validate((object) ['version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('definition.yaml'));
        $this->addToAssertionCount(1);
    }

    /**
     * @throws JsonException
     */
    public function testValidateAcceptsRelativeLocalSchema(): void
    {
        $project = new ProjectDirectory();
        $project->put('schemas/team.schema.json', '{"type":"object","required":["items"]}');
        (new SchemaValidator())->validate((object) ['$schema' => 'schemas/team.schema.json', 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('definition.yaml'));
        $this->addToAssertionCount(1);
    }

    /**
     * @throws JsonException
     */
    public function testValidateResolvesRelativeSchemaAgainstDocumentDirectory(): void
    {
        $project = new ProjectDirectory();
        $project->put('team.schema.json', '{"type":"object","required":["category"]}');
        $project->put('nested/team.schema.json', '{"type":"object","required":["team"]}');
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('nested/definition.yaml') . ': schema validation failed: {"/":["The required properties (team) are missing"]}');
        (new SchemaValidator())->validate((object) ['$schema' => 'team.schema.json', 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('nested/definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testValidateReadsAbsoluteLocalSchema(): void
    {
        $project = new ProjectDirectory();
        $schema = $project->put('team.schema.json', '{"type":"object","required":["category"]}');
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('nested/definition.yaml') . ': schema validation failed: {"/":["The required properties (category) are missing"]}');
        (new SchemaValidator())->validate((object) ['$schema' => $schema, 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('nested/definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsUnreadableLocalSchema(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.yaml') . ': cannot read schema ' . $project->path('urn:team'));
        (new SchemaValidator())->validate((object) ['$schema' => 'urn:team', 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsLocalSchemaThatIsNotJson(): void
    {
        $project = new ProjectDirectory();
        $project->put('team.schema.json', 'type: object');
        $this->expectException(JsonException::class);
        (new SchemaValidator())->validate((object) ['$schema' => 'team.schema.json', 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testValidateAppliesBundledSchemaBeforeDeclaredOne(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.yaml') . ': schema validation failed: {"/%24schema":["The data (integer) must match the type: string"]}');
        (new SchemaValidator())->validate((object) ['$schema' => 1, 'version' => 1, 'source' => null, 'items' => []], 'definition', $project->path('definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsDocumentBreakingBundledSchema(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('requirements.yaml: schema validation failed: {"/":["The required properties (definitions) are missing"]}');
        (new SchemaValidator())->validate((object) ['version' => 1], 'config', 'requirements.yaml');
    }

    #[DataProvider('providerBundledSchemas')]
    public function testPathLocatesBundledSchema(string $name): void
    {
        self::assertSame(dirname(__DIR__, 3) . '/schemas/' . $name, SchemaValidator::path($name));
        self::assertFileExists(SchemaValidator::path($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerBundledSchemas(): array
    {
        return [
            'configuration' => ['config.schema.json'],
            'definition' => ['definition.schema.json'],
            'document profile' => ['definition.document.yaml'],
        ];
    }

    public function testBaseIsPublishedSchemaLocation(): void
    {
        self::assertSame('https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/', SchemaValidator::BASE);
    }
    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvalidRunPolicies')]
    public function testRejectsInvalidTestRunPolicies(mixed $run): void
    {
        $workspace = new ProjectDirectory();
        $data = (new Loader())->document($workspace->directory . '/definition.yaml');
        $data['items'] = [[...ProjectDirectory::item(), 'tests' => [['runner' => 'unit', 'target' => 'Example::testOne', 'run' => $run]]]];
        $workspace->write('definition.yaml', $data);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('/items/0/tests/0/run');
        (new Loader())->load($workspace->directory . '/requirements.yaml');
    }

    /**
     * @return list<array{mixed}>
     */
    public static function providerInvalidRunPolicies(): array
    {
        return [['never'], [''], [false], [null], [1]];
    }
}
