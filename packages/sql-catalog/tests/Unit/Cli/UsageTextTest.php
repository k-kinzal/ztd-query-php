<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\UsageText;
use SqlCatalog\Extension\DoctrineExtension;
use SqlCatalog\Extension\ExtensionRegistry;
use SqlCatalog\Extension\LaravelExtension;
use SqlCatalog\Extension\MysqliExtension;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Reporter\HtmlReporter;
use SqlCatalog\Reporter\JsonReporter;
use SqlCatalog\Reporter\ReporterRegistry;
use SqlCatalog\Reporter\TextReporter;

#[CoversClass(UsageText::class)]
#[UsesClass(DoctrineExtension::class)]
#[UsesClass(ExtensionRegistry::class)]
#[UsesClass(LaravelExtension::class)]
#[UsesClass(MysqliExtension::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(HtmlReporter::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(ReporterRegistry::class)]
#[UsesClass(TextReporter::class)]
final class UsageTextTest extends TestCase
{
    public function testHelpDocumentsEveryOptionTheParserTakes(): void
    {
        $help = (new UsageText(ExtensionRegistry::withBuiltins(), ReporterRegistry::withBuiltins()))->help();
        self::assertStringContainsString('--output', $help);
        self::assertStringContainsString('--reporter', $help);
        self::assertStringContainsString('--extension', $help);
        self::assertStringContainsString('--namespace', $help);
        self::assertStringContainsString('--method', $help);
        self::assertStringContainsString('--kind', $help);
        self::assertStringContainsString('--table', $help);
        self::assertStringContainsString('--sink', $help);
        self::assertStringContainsString('--severity', $help);
        self::assertStringContainsString('--fail-on', $help);
        self::assertStringContainsString('--exclude', $help);
        self::assertStringContainsString('--root', $help);
    }

    public function testExtensionsAreListedWithTheirDescriptions(): void
    {
        $listed = (new UsageText(ExtensionRegistry::withBuiltins(), ReporterRegistry::withBuiltins()))->extensions();
        self::assertStringContainsString('pdo', $listed);
        self::assertStringContainsString('laravel', $listed);
    }

    public function testReportersAreListedWithTheirDescriptions(): void
    {
        $listed = (new UsageText(ExtensionRegistry::withBuiltins(), ReporterRegistry::withBuiltins()))->reporters();
        self::assertStringContainsString('json', $listed);
        self::assertStringContainsString('html', $listed);
    }
}
