<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares the absent-library error despite the process-local errno exposed by MySQL 5.x.
 *
 * Black-box observations of 5.6.51 and 5.7.44 return errno 2 or 11 for the same failed load,
 * including successive executions in the same process. Only that integer is interchangeable:
 * SQL error 1126, HY000, the library, path, complete loader message and warnings stay exact.
 * All result sets and table effects remain part of the ordinary differential comparison.
 */
final class LibraryErrors
{
    /**
     * @param array<string, mixed> $observation An unchanged raw server observation
     * @return array<string, mixed> A comparison copy with the validated errno represented by its allowed set
     */
    public function comparable(array $observation, string $version): array
    {
        $error = $observation['error'] ?? null;
        if (!str_starts_with($version, '5.') || !is_array($error) || ($error[0] ?? null) !== 1126 || ($error[1] ?? null) !== 'HY000' || !is_string($error[2] ?? null)) {
            return $observation;
        }
        $message = $error[2];
        if (preg_match("/\ACan't open shared library '.*' \(errno: (?:2|11) .+: cannot open shared object file: No such file or directory\)\z/s", $message) !== 1) {
            return $observation;
        }
        $comparable = preg_replace('/\(errno: (?:2|11) /', '(errno: {2|11} ', $message, 1);
        $error[2] = $comparable;
        $observation['error'] = $error;
        $warnings = $observation['warnings'] ?? null;
        if (is_array($warnings)) {
            foreach ($warnings as $index => $condition) {
                if ($condition === ['Error', 1126, $message]) {
                    $warnings[$index] = ['Error', 1126, $comparable];
                }
            }
            $observation['warnings'] = $warnings;
        }

        return $observation;
    }
}
