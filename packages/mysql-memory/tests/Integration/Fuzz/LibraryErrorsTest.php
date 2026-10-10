<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\LibraryErrors;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class LibraryErrorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function providerMessages(): iterable
    {
        $two = "Can't open shared library 'text' (errno: 2 /plugin/text: cannot open shared object file: No such file or directory)";
        $eleven = str_replace('errno: 2 ', 'errno: 11 ', $two);
        yield '5.6 process errno' => ['5.6.51', $two, $eleven, true];
        yield '5.7 process errno' => ['5.7.44', $two, $eleven, true];
        yield 'modern release stays exact' => ['8.4.7', $two, $eleven, false];
        yield 'unobserved errno fails' => ['5.7.44', $two, str_replace('errno: 2 ', 'errno: 13 ', $two), false];
        yield 'different path fails' => ['5.7.44', $two, str_replace('/plugin/', '/other/', $two), false];
        yield 'different loader failure fails' => ['5.7.44', $two, str_replace('No such file or directory', 'Permission denied', $two), false];
        yield 'different library fails' => ['5.7.44', $two, str_replace("'text'", "'other'", $two), false];
    }

    #[DataProvider('providerMessages')]
    public function testComparableAllowsOnlyTheObservedErrnoVariants(string $version, string $first, string $second, bool $equal): void
    {
        $contract = new LibraryErrors();
        $left = ['error' => [1126, 'HY000', $first], 'warnings' => [['Error', 1126, $first]], 'tables' => ['t' => [[1]]]];
        $right = ['error' => [1126, 'HY000', $second], 'warnings' => [['Error', 1126, $second]], 'tables' => ['t' => [[1]]]];

        self::assertSame($equal, $contract->comparable($left, $version) === $contract->comparable($right, $version));
        self::assertSame(['t' => [[1]]], $contract->comparable($left, $version)['tables']);
        self::assertSame($first, $left['error'][2]);
    }

    public function testComparablePreservesDifferentErrorCodesAndWarningLevels(): void
    {
        $message = "Can't open shared library 'text' (errno: 2 /plugin/text: cannot open shared object file: No such file or directory)";
        $observation = ['error' => [1128, 'HY000', $message], 'warnings' => [['Warning', 1126, $message]]];

        self::assertSame($observation, (new LibraryErrors())->comparable($observation, '5.7.44'));
    }
}
