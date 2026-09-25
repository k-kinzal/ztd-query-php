<?php

declare(strict_types=1);

namespace Requirements\Test;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads the verdict of a test run from its JUnit XML reports.
 *
 * Unsafe, unreadable or malformed reports, reports without executed cases, and any failure,
 * error or skip turn the run into a non-passing result.
 */
final class JUnit
{
    /**
     * Reads the reports of one run.
     *
     * @param list<string> $files The report files the run wrote
     * @param int $exitCode The exit code of the run
     * @param string $output The run's output, reported when it did not pass
     *
     * @return TestResult The verdict and the number of executed cases
     */
    public function read(array $files, int $exitCode, string $output): TestResult
    {
        $count = 0;
        $failed = false;
        foreach ($files as $file) {
            $xml = file_get_contents($file);
            if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
                return new TestResult('error', 0, 'Unsafe or unreadable JUnit report.');
            }
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $valid = $document->loadXML($xml, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$valid || !in_array($document->documentElement?->tagName, ['testsuites', 'testsuite'], true)) {
                return new TestResult('error', 0, 'Malformed JUnit report.');
            }
            $xpath = new DOMXPath($document);
            $cases = $xpath->query('//testcase');
            if ($cases === false) {
                return new TestResult('error', 0, 'Cannot read JUnit cases.');
            }
            foreach ($cases as $case) {
                if ($case instanceof DOMElement) {
                    ++$count;
                    $status = $case->getAttribute('status');
                    $failed = $failed || ($status !== '' && !in_array($status, ['passed', 'success'], true));
                }
            }
            $defects = $xpath->query('//failure | //error | //skipped');
            $failed = $failed || ($defects !== false && $defects->length > 0);
        }
        if ($count === 0) {
            return new TestResult('error', 0, 'No executed tests in fresh JUnit reports. ' . trim($output));
        }
        return new TestResult($exitCode === 0 && !$failed ? 'passed' : 'failed', $count, $exitCode === 0 && !$failed ? '' : trim($output));
    }
}
