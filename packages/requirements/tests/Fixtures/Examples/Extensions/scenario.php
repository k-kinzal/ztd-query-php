<?php

declare(strict_types=1);

$options = getopt('', ['case:', 'junit:']);
if ($options === false || !isset($options['case'], $options['junit']) || !is_string($options['case']) || !is_string($options['junit'])) {
    fwrite(STDERR, "Usage: php scenario.php --case ascii-uppercase --junit FILE\n");
    exit(2);
}
if ($options['case'] !== 'ascii-uppercase') {
    fwrite(STDERR, "Unknown case.\n");
    exit(2);
}
$passed = strtoupper('example') === 'EXAMPLE';
$failure = $passed ? '' : '<failure message="Expected uppercase ASCII letters"/>';
$xml = '<testsuite tests="1"><testcase name="ascii-uppercase">' . $failure . '</testcase></testsuite>';
if (file_put_contents($options['junit'], $xml) === false) {
    exit(2);
}
exit($passed ? 0 : 1);
