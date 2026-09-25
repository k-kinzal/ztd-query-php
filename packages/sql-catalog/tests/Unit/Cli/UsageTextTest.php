<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\UsageText;
use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Reporter\ReporterRegistry;
use SqlCatalog\Extension\Doctrine\DoctrineExtension;
use SqlCatalog\Extension\Laravel\LaravelExtension;
use SqlCatalog\Extension\Mysqli\MysqliExtension;
use SqlCatalog\Extension\Pdo\PdoExtension;
use SqlCatalog\Reporter\Html\HtmlReporter;
use SqlCatalog\Reporter\Json\JsonReporter;
use SqlCatalog\Reporter\Text\TextReporter;

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
#[UsesClass(\SqlCatalog\Extension\WordPress\WordPressExtension::class)]
final class UsageTextTest extends TestCase
{
    public function testHelpDocumentsEveryOptionTheParserTakes(): void
    {
        $help = (new UsageText(\SqlCatalog\Facade\Builtins::extensions(), \SqlCatalog\Facade\Builtins::reporters()))->help();
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
        $listed = (new UsageText(\SqlCatalog\Facade\Builtins::extensions(), \SqlCatalog\Facade\Builtins::reporters()))->extensions();
        self::assertStringContainsString('pdo', $listed);
        self::assertStringContainsString('laravel', $listed);
    }

    public function testReportersAreListedWithTheirDescriptions(): void
    {
        $listed = (new UsageText(\SqlCatalog\Facade\Builtins::extensions(), \SqlCatalog\Facade\Builtins::reporters()))->reporters();
        self::assertStringContainsString('json', $listed);
        self::assertStringContainsString('html', $listed);
    }
}
