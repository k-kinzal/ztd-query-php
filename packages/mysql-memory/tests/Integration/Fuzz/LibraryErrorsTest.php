<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\LibraryErrors;
use JsonException;
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
        yield '8.0 process errno' => ['8.0.44', str_replace('errno: 2 ', 'errno: 0 ', $two), $eleven, true];
        yield '8.1 process errno' => ['8.1.0', str_replace('errno: 2 ', 'errno: 0 ', $two), $eleven, true];
        yield '8.1 unobserved errno stays exact' => ['8.1.0', $two, $eleven, false];
        yield '8.1 different path stays exact' => ['8.1.0', str_replace('errno: 2 ', 'errno: 0 ', $two), str_replace('/plugin/', '/other/', $eleven), false];
        yield '8.2 process errno' => ['8.2.0', str_replace('errno: 2 ', 'errno: 0 ', $two), $eleven, true];
        yield '8.2 unobserved errno stays exact' => ['8.2.0', $two, $eleven, false];
        yield '8.2 permission errno stays exact' => ['8.2.0', str_replace('errno: 2 ', 'errno: 13 ', $two), $eleven, false];
        yield '8.2 different path stays exact' => ['8.2.0', str_replace('errno: 2 ', 'errno: 0 ', $two), str_replace('/plugin/', '/other/', $eleven), false];
        yield '8.0 unobserved errno stays exact' => ['8.0.44', $two, $eleven, false];
        yield 'legacy unobserved zero stays exact' => ['5.7.44', $two, str_replace('errno: 2 ', 'errno: 0 ', $two), false];
        yield '8.4 process errno' => ['8.4.7', str_replace('errno: 2 ', 'errno: 0 ', $two), $eleven, true];
        yield '8.4 permission errno stays exact' => ['8.4.7', str_replace('errno: 2 ', 'errno: 13 ', $two), $eleven, false];
        yield '8.4 different path stays exact' => ['8.4.7', str_replace('errno: 2 ', 'errno: 0 ', $two), str_replace('/plugin/', '/other/', $eleven), false];
        yield '8.4 unobserved errno stays exact' => ['8.4.7', $two, $eleven, false];
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

    public function testComparableKeepsErrnoTextInsideTheLibraryNameExact(): void
    {
        $first = "Can't open shared library 'text (errno: 2 ' (errno: 2 /plugin/text: cannot open shared object file: No such file or directory)";
        $second = "Can't open shared library 'text (errno: 11 ' (errno: 2 /plugin/text: cannot open shared object file: No such file or directory)";

        self::assertNotSame((new LibraryErrors())->comparable(['error' => [1126, 'HY000', $first]], '5.7.44'), (new LibraryErrors())->comparable(['error' => [1126, 'HY000', $second]], '5.7.44'));
    }

    /**
     * @throws JsonException When the saved observations are malformed
     */
    public function testComparableRetainsTheCompleteGeneratedLibraryName(): void
    {
        /** @var array{campaignObservations: list<array{error: array{int, string, string}, warnings: list<array{string, int, string}>}>, independentObservations: list<array{instance: int, error: array{int, string, string}, warnings: list<array{string, int, string}>}>} $saved */
        $saved = json_decode((string) file_get_contents(__DIR__ . '/fixtures/library-errno-8.4.json'), true, 512, JSON_THROW_ON_ERROR);
        $contract = new LibraryErrors();
        $first = $contract->comparable($saved['campaignObservations'][0], '8.4.7');
        self::assertSame($first, $contract->comparable($saved['campaignObservations'][1], '8.4.7'));
        self::assertSame($first, $contract->comparable(array_diff_key($saved['independentObservations'][0], ['instance' => true]), '8.4.7'));
        self::assertSame($first, $contract->comparable(array_diff_key($saved['independentObservations'][1], ['instance' => true]), '8.4.7'));
    }
}
