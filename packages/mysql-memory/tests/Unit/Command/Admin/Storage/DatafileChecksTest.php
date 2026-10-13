<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Storage;

use MySqlMemory\Command\Admin\Storage\DatafileChecks;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Registry\Tablespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatafileChecks::class)]
#[Small]
final class DatafileChecksTest extends TestCase
{
    public function testPathNormalizesSeparatorsAndStopsAtTheFirstNullByte(): void
    {
        self::assertSame('a/b.ibd', (new DatafileChecks())->path("a\\b.ibd\0ignored"));
    }

    public function testIdentityResolvesLeadingDotDirectoriesOnly(): void
    {
        self::assertSame('a.ibd', (new DatafileChecks())->identity('././a.ibd'));
        self::assertSame('../a.ibd', (new DatafileChecks())->identity('../a.ibd'));
    }

    public function testReasonsSeparateShortNamesFromWrongExtensionsBeforeDirectoryErrors(): void
    {
        $checks = new DatafileChecks();
        $registry = new Registry();

        self::assertSame([
            "The ADD DATAFILE filepath must end with '.ibd'.",
            'The directory does not exist or is incorrect.',
            'The DATAFILE location cannot be under the datadir.',
        ], $checks->reasons('a\\abcde', '.ibd', $registry));
        self::assertSame([
            'The ADD DATAFILE filepath does not have a proper filename.',
            "The ADD DATAFILE filepath must end with '.ibd'.",
        ], $checks->reasons('abcd', '.ibd', $registry));
    }

    public function testReasonsReportsAnExistingFileThroughAnotherSpelling(): void
    {
        $registry = new Registry();
        $registry->tablespaces['one'] = new Tablespace('one', false, 'a.ibd');

        self::assertSame(['The ADD DATAFILE filepath already exists.'], (new DatafileChecks())->reasons('./a.ibd', '.ibd', $registry));
    }

    public function testReasonsReportsColonAndCircularDirectoryNames(): void
    {
        $checks = new DatafileChecks();
        $registry = new Registry();

        self::assertSame(["Incorrect File Name 'a:/b.ibd'.", "Invalid use of ':'."], $checks->reasons('a:\\b.ibd', '.ibd', $registry));
        self::assertSame([
            'The ADD DATAFILE filepath cannot contain circular directory references.',
            'The directory does not exist or is incorrect.',
            'The DATAFILE location cannot be under the datadir.',
        ], $checks->reasons('a/../b.ibd', '.ibd', $registry));
        self::assertSame(['The DATAFILE location must be in a known directory.'], $checks->reasons('../b.ibd', '.ibd', $registry));
    }
}
