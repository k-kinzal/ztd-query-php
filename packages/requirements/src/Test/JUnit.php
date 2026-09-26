<?php

declare(strict_types=1);

namespace Requirements\Test;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class JUnit
{
    /** @param list<string> $files */
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
