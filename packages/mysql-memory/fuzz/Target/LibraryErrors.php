<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares the absent-library error despite MySQL's process-local errno.
 *
 * Black-box observations of 5.6.51 and 5.7.44 return errno 2 or 11 for the same failed load,
 * including independent instances. The 8.0.44, 8.1.0 and 8.2.0 supplied corpora and
 * an 8.4.7 unseeded campaign also observed 0 and 11 for identical requests. Fresh
 * independent 8.4.7 instances repeated the retained request with errno 11.
 * The 9.1.0 supplied corpus observed 0; two fresh independent 9.1.0 instances
 * repeated that exact request twenty times each with errno 11.
 * Only these release-specific integers are interchangeable:
 * SQL error 1126, HY000, the library, path, complete loader message and warnings stay exact.
 * All result sets and table effects remain part of the ordinary differential comparison.
 */
final class LibraryErrors
{
    /**
     * The recorded comparison rule for an observed process-local loader errno.
     */
    public const CONTRACT = 'missing-library-process-errno';

    /**
     * @param array<string, mixed> $observation An unchanged raw server observation
     * @return array<string, mixed> A comparison copy with the validated errno represented by its allowed set
     */
    public function comparable(array $observation, string $version): array
    {
        $error = $observation['error'] ?? null;
        $allowed = in_array($version, ['5.6.51', '5.7.44'], true) ? '2|11' : (in_array($version, ['8.0.44', '8.1.0', '8.2.0', '8.4.7', '9.1.0'], true) ? '0|11' : null);
        if ($allowed === null || !is_array($error) || ($error[0] ?? null) !== 1126 || ($error[1] ?? null) !== 'HY000' || !is_string($error[2] ?? null)) {
            return $observation;
        }
        $message = $error[2];
        if (preg_match("/\A(Can't open shared library '.*' \(errno: )(?:" . $allowed . ")( .+: cannot open shared object file: No such file or directory\))\z/s", $message, $parts) !== 1) {
            return $observation;
        }
        $comparable = $parts[1] . '{' . self::CONTRACT . '}' . $parts[2];
        $error[2] = $comparable;
        $observation['error'] = $error;
        $warnings = $observation['warnings'] ?? null;
        if (is_array($warnings)) {
            foreach ($warnings as $index => $condition) {
                if ($condition === ['Error', 1126, $message] || $condition === ['Error', '1126', $message]) {
                    $condition[2] = $comparable;
                    $warnings[$index] = $condition;
                }
            }
            $observation['warnings'] = $warnings;
        }

        return $observation;
    }
}
