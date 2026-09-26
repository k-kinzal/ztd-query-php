<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * A PHPUnit test class with passing, failing, skipped and data-provider methods, written into
 * a directory so the PHPUnit runner can execute it.
 *
 * Its methods are Sample\PassingTest::testPass, testPassSuffix (which fails and must never be
 * selected by testPass), testFailure, testSkip and testData with two data sets.
 */
final class PhpUnitSuite
{
    /**
     * Writes PassingTest.php into a directory.
     *
     * @param string $directory The directory
     *
     * @return list<string> The command running the file with the package's PHPUnit and no configuration
     */
    public static function write(string $directory): array
    {
        file_put_contents($directory . '/PassingTest.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Sample;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PassingTest extends TestCase
{
    public function testPass(): void
    {
        self::assertSame('ABC', strtoupper('abc'));
    }

    public function testPassSuffix(): void
    {
        self::fail('A similarly named test must not be selected.');
    }

    public function testFailure(): void
    {
        self::fail('Expected failure.');
    }

    public function testSkip(): void
    {
        self::markTestSkipped('Expected skip.');
    }

    #[DataProvider('values')]
    public function testData(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    public static function values(): array
    {
        return [[1], [2]];
    }
}
PHP);
        return [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/bin/phpunit', '--no-configuration', $directory . '/PassingTest.php'];
    }
}
