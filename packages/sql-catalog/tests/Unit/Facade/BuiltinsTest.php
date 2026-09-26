<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\Builtins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Sql\Dialects::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Reporter\Html\SqlFormatter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Catalog\StatementPart::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\MySql\SqlFormatter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\PostgreSql\SqlFormatter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Platform\Sqlite\SqlFormatter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Extension\ExtensionRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Reporter\ReporterRegistry::class)]
final class BuiltinsTest extends TestCase
{
    public function testExtensionsRegistersTheShippedImplementationsAndDefaults(): void
    {
        $extensions = \SqlCatalog\Facade\Builtins::extensions();
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo', 'wordpress'], $extensions->names());
        self::assertSame(['pdo', 'mysqli'], $extensions->defaultNames());
    }

    public function testReportersRegistersTheShippedFormats(): void
    {
        self::assertSame(['html', 'json', 'text'], \SqlCatalog\Facade\Builtins::reporters()->names());
    }

    public function testDialectsSelectsIndependentPolicies(): void
    {
        $dialects = \SqlCatalog\Facade\Builtins::dialects();
        self::assertSame('`', $dialects->find('mysql')?->identifierQuote());
        self::assertSame('"', $dialects->find('pgsql')?->identifierQuote());
        self::assertSame('insert or ignore into ', $dialects->find('sqlite')?->insertPrefix(true));
    }

    public function testFormattersCoverAllBuiltInLanguages(): void
    {
        self::assertCount(3, \SqlCatalog\Facade\Builtins::formatters());
    }

    public function testSqlFormatterKeepsTheDefaultExpandedPresentation(): void
    {
        $parts = \SqlCatalog\Facade\Builtins::sqlFormatter()->format([new \SqlCatalog\Core\Catalog\StatementPart('SELECT 1')]);
        self::assertSame("SELECT\n    1", $parts[0]->text);
    }
}
