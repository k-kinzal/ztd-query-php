<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\JsonSchemaFile;
use Requirements\Input\InvalidInputException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(JsonSchemaFile::class)]
#[Small]
final class JsonSchemaFileTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProvider('providerAccepted')]
    public function testValidateAcceptsConformingDocument(string $schema, mixed $data): void
    {
        $project = new ProjectDirectory();
        (new JsonSchemaFile())->validate($data, $project->put('schema.json', $schema), 'doc.yaml');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function providerAccepted(): array
    {
        return [
            'object schema' => ['{"type":"object","required":["a"]}', (object) ['a' => 1]],
            'empty object schema' => ['{}', 'anything'],
            'true schema' => ['true', (object) ['a' => 1]],
            'scalar document' => ['{"type":"integer"}', 3],
        ];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerRejected')]
    public function testValidateRejectsDocumentBreakingSchema(string $schema, mixed $data, string $message): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('schema.json', $schema);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new JsonSchemaFile())->validate($data, $file, 'doc.yaml');
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function providerRejected(): array
    {
        return [
            'missing property' => ['{"type":"object","required":["a"]}', (object) [], 'doc.yaml: schema validation failed: {"/":["The required properties (a) are missing"]}'],
            'false schema' => ['false', (object) [], 'doc.yaml: schema validation failed: {"/":["Data not allowed"]}'],
            'nested path with unescaped slashes' => ['{"properties":{"u":{"const":"a/b"}}}', (object) ['u' => 'x'], 'doc.yaml: schema validation failed: {"/u":["The data must match the const value"]}'],
        ];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerUnusableSchemas')]
    public function testValidateRejectsSchemaThatIsNeitherObjectNorBoolean(string $schema): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('schema.json', $schema);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("doc.yaml: expected an object or Boolean schema in $file");
        (new JsonSchemaFile())->validate((object) [], $file, 'doc.yaml');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnusableSchemas(): array
    {
        return [
            'number' => ['1'],
            'string' => ['"object"'],
            'list' => ['[]'],
            'null' => ['null'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsMissingSchema(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.yaml: cannot read schema ' . $project->path('missing.json'));
        (new JsonSchemaFile())->validate((object) [], $project->path('missing.json'), 'doc.yaml');
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsDirectoryAsSchema(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.yaml: cannot read schema ' . $project->directory);
        (new JsonSchemaFile())->validate((object) [], $project->directory, 'doc.yaml');
    }

    /**
     * @throws JsonException
     */
    public function testValidateRejectsSchemaThatIsNotJson(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('schema.json', '{"type":');
        $this->expectException(JsonException::class);
        (new JsonSchemaFile())->validate((object) [], $file, 'doc.yaml');
    }
}
