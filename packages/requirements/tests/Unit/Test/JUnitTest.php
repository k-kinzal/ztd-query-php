<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Test\JUnit;
use Requirements\Test\TestResult;
use Tests\Fake\ProjectDirectory;

#[CoversClass(JUnit::class)]
#[UsesClass(TestResult::class)]
#[Small]
final class JUnitTest extends TestCase
{
    #[DataProvider('providerReadCannotPassWithoutExecutedPassingCases')]
    public function testReadCannotPassWithoutExecutedPassingCases(string $xml, string $status): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', $xml);
        self::assertSame($status, (new JUnit())->read([$file], 0, '')->status);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerReadCannotPassWithoutExecutedPassingCases(): array
    {
        return [
            ['<testsuite/>', 'error'],
            ['<testsuite><testcase/></testsuite>', 'passed'],
            ['<testsuite><testcase><skipped/></testcase></testsuite>', 'failed'],
            ['<testsuite><testcase status="pending"/></testsuite>', 'failed'],
            ['<testsuite><testcase><error/></testcase></testsuite>', 'failed'],
            ['<testsuite><testcase>', 'error'],
            ['<!DOCTYPE a><testsuite><testcase/></testsuite>', 'error'],
        ];
    }

    #[DataProvider('providerReadCountsPassingCases')]
    public function testReadCountsPassingCases(string $xml, int $tests): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', $xml);
        self::assertSame(['status' => 'passed', 'tests' => $tests, 'message' => ''], (new JUnit())->read([$file], 0, "output\n")->toArray());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function providerReadCountsPassingCases(): array
    {
        return [
            'testsuite root' => ['<testsuite><testcase/></testsuite>', 1],
            'testsuites root' => ['<?xml version="1.0"?><testsuites><testsuite><testcase name="a"/><testcase name="b"/></testsuite></testsuites>', 2],
            'nested suites' => ['<testsuites><testsuite><testsuite><testcase/></testsuite><testcase/></testsuite></testsuites>', 2],
            'passed status' => ['<testsuite><testcase status="passed"/></testsuite>', 1],
            'success status' => ['<testsuite><testcase status="success"/></testsuite>', 1],
            'empty status' => ['<testsuite><testcase status=""/></testsuite>', 1],
            'system output' => ['<testsuite><testcase><system-out>text</system-out></testcase></testsuite>', 1],
        ];
    }

    #[DataProvider('providerReadFailsOnDefects')]
    public function testReadFailsOnDefects(string $xml, int $tests): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', $xml);
        self::assertSame(['status' => 'failed', 'tests' => $tests, 'message' => 'Expected failure.'], (new JUnit())->read([$file], 0, "\n Expected failure.\n")->toArray());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function providerReadFailsOnDefects(): array
    {
        return [
            'failure' => ['<testsuite><testcase><failure>boom</failure></testcase></testsuite>', 1],
            'failed status before a passing case' => ['<testsuite><testcase status="failed"/><testcase/></testsuite>', 2],
            'uppercase status' => ['<testsuite><testcase status="PASSED"/></testsuite>', 1],
            'error outside cases' => ['<testsuites><testsuite><testcase/><error/></testsuite></testsuites>', 1],
        ];
    }

    public function testReadFailsOnANonzeroExitCode(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', '<testsuite><testcase/></testsuite>');
        self::assertSame(['status' => 'failed', 'tests' => 1, 'message' => 'Exit 1.'], (new JUnit())->read([$file], 1, " Exit 1.\n")->toArray());
    }

    public function testReadSumsCasesOfEveryReport(): void
    {
        $project = new ProjectDirectory();
        $first = $project->put('first.xml', '<testsuite><testcase/></testsuite>');
        $second = $project->put('second.xml', '<testsuite><testcase/><testcase/></testsuite>');
        self::assertSame(['status' => 'passed', 'tests' => 3, 'message' => ''], (new JUnit())->read([$first, $second], 0, '')->toArray());
    }

    public function testReadFailsWhenAnyReportFails(): void
    {
        $project = new ProjectDirectory();
        $first = $project->put('first.xml', '<testsuite><testcase><failure/></testcase></testsuite>');
        $second = $project->put('second.xml', '<testsuite><testcase/></testsuite>');
        self::assertSame(['status' => 'failed', 'tests' => 2, 'message' => 'out'], (new JUnit())->read([$first, $second], 0, 'out')->toArray());
    }

    public function testReadRejectsAnUnsafeLaterReport(): void
    {
        $project = new ProjectDirectory();
        $first = $project->put('first.xml', '<testsuite><testcase/></testsuite>');
        $second = $project->put('second.xml', '<testsuite><testcase/></testsuite><!ENTITY a "b">');
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'Unsafe or unreadable JUnit report.'], (new JUnit())->read([$first, $second], 0, '')->toArray());
    }

    #[DataProvider('providerReadRejectsUnsafeReports')]
    public function testReadRejectsUnsafeReports(string $xml): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', $xml);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'Unsafe or unreadable JUnit report.'], (new JUnit())->read([$file], 0, '')->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerReadRejectsUnsafeReports(): array
    {
        return [
            'doctype' => ['<!DOCTYPE a><testsuite><testcase/></testsuite>'],
            'lowercase doctype' => ['<!doctype a><testsuite><testcase/></testsuite>'],
            'entity' => ['<testsuite><!ENTITY a "b"><testcase/></testsuite>'],
            'lowercase entity' => ['<testsuite><!entity a "b"><testcase/></testsuite>'],
        ];
    }

    #[DataProvider('providerReadRejectsMalformedReports')]
    public function testReadRejectsMalformedReports(string $xml): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', $xml);
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'Malformed JUnit report.'], (new JUnit())->read([$file], 0, '')->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerReadRejectsMalformedReports(): array
    {
        return [
            'unclosed' => ['<testsuite><testcase>'],
            'text' => ['passed'],
            'other root' => ['<results><testcase/></results>'],
            'testcase root' => ['<testcase/>'],
            'empty' => [''],
            'blank' => ["  \n"],
        ];
    }

    public function testReadLeavesNoLibxmlErrorsBehind(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', '<testsuite><testcase>');
        (new JUnit())->read([$file], 0, '');
        self::assertSame([], libxml_get_errors());
        self::assertFalse(libxml_use_internal_errors(false));
    }

    public function testReadReportsNoExecutedTestsWithTheOutput(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('report.xml', '<testsuites><testsuite name="empty"/></testsuites>');
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'No executed tests in fresh JUnit reports. No tests executed!'], (new JUnit())->read([$file], 0, "\nNo tests executed!\n")->toArray());
    }

    public function testReadReportsNoExecutedTestsWithoutReports(): void
    {
        self::assertSame(['status' => 'error', 'tests' => 0, 'message' => 'No executed tests in fresh JUnit reports. '], (new JUnit())->read([], 0, '')->toArray());
    }
}
