<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Deriver\Configuration\Loader;
use Deriver\Exception\ConfigurationException;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LoaderTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        vfsStream::setup('project', null, ['config' => ['deliver.yaml' => "paths: [src, /external]\nphpVersion: '8.4'\n"]]);
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('is_file')->setFunction(static fn (string $path): bool => is_file('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('file_get_contents')->setFunction(static fn (string $path): string|false => file_get_contents('vfs:/' . $path))->build()->enable();
    }

    public function testLoadAnchorsRelativeRootsToTheConfigurationDirectory(): void
    {
        $configuration = (new Loader())->load('/project/config/deliver.yaml');
        self::assertSame(['/project/config/src', '/external'], $configuration->paths);
        self::assertSame('8.4', $configuration->phpVersion);
    }

    public function testLoadWithoutAPathUsesDefaults(): void
    {
        self::assertSame(['.'], (new Loader())->load()->paths);
    }

    public function testLoadAnchorsDefaultRootForAnEmptyFile(): void
    {
        file_put_contents('vfs://project/config/deliver.yaml', '');
        self::assertSame(['/project/config/.'], (new Loader())->load('/project/config/deliver.yaml')->paths);
    }

    public function testLoadRejectsMissingFiles(): void
    {
        $this->expectException(ConfigurationException::class);
        (new Loader())->load('/project/missing.yaml');
    }

    public function testLoadRejectsMalformedYamlWithItsCause(): void
    {
        file_put_contents('vfs://project/config/deliver.yaml', "paths: [\n");
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cannot load configuration');
        (new Loader())->load('/project/config/deliver.yaml');
    }

    public function testLoadRejectsScalarDocuments(): void
    {
        file_put_contents('vfs://project/config/deliver.yaml', 'not-a-mapping');
        $this->expectException(ConfigurationException::class);
        (new Loader())->load('/project/config/deliver.yaml');
    }

    public function testLoadRejectsObjectTags(): void
    {
        file_put_contents('vfs://project/config/deliver.yaml', "environment: {APP_ENV: !php/object 'O:8:\"stdClass\":0:{}'}");
        $this->expectException(ConfigurationException::class);
        (new Loader())->load('/project/config/deliver.yaml');
    }

    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
