<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakePlatform;
use Tests\Fake\FakeSchemaReflector;
use ZtdQuery\Config\ZtdConfig;

#[CoversNothing]
final class PlatformTest extends TestCase
{
    public function testCreateRewriterSuppliesTheCoreExecutionPipeline(): void
    {
        $executor = new \ZtdQuery\QueryExecutor(new FakeConnection([]), new FakePlatform(), ZtdConfig::default());

        self::assertTrue($executor->session()->isEnabled());
    }

    public function testReflectSchemaReadsEveryTableTheDatabaseAlreadyHas(): void
    {
        $platform = new FakePlatform(new FakeSchemaReflector([
            'users' => 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY)',
        ]));

        $executor = new \ZtdQuery\QueryExecutor(new FakeConnection([]), $platform, ZtdConfig::default());

        self::assertNotNull($executor->session()->tableDefinition('users'));
    }

    public function testCreateAnswersASessionThatHasSimulatedNothingYet(): void
    {
        $platform = new FakePlatform(new FakeSchemaReflector([
            'users' => 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY)',
        ]));

        $executor = new \ZtdQuery\QueryExecutor(new FakeConnection([]), $platform, ZtdConfig::default());

        self::assertFalse($executor->session()->lastInsertId());
    }

    public function testReflectViewsReturnsAnIndependentCatalog(): void
    {
        $platform = new FakePlatform();
        $connection = new FakeConnection();
        self::assertNotSame($platform->reflectViews($connection), $platform->reflectViews($connection));
    }

    public function testCopySupportCanBeAbsent(): void
    {
        self::assertNull((new FakePlatform())->copySupport());
    }

    public function testParameterBindingCompilerCanBeAbsent(): void
    {
        self::assertNull((new FakePlatform())->parameterBindingCompiler());
    }

    public function testResultColumnTypeResolverIsExplicitWithoutNativeMetadata(): void
    {
        self::assertInstanceOf(\ZtdQuery\Platform\MissingResultColumnTypeResolver::class, (new FakePlatform())->resultColumnTypeResolver());
    }
}
