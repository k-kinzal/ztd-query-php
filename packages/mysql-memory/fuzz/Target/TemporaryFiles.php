<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Compares unavailable physical filenames in legacy generated-column diagnostics.
 *
 * Two independent 5.7.44 instances returned /tmp/#sql1_3_0.frm and
 * /tmp/#sql1_4_0.frm for the same CREATE TEMPORARY TABLE, and ./d/#sql-1_3.frm
 * and ./d/#sql-1_4.frm for the same ALTER TABLE. An unrelated connection accounts
 * for the different instance-local names. Memory has no corresponding file.
 * Ordinary logical table filenames, error numbers, SQLSTATEs, warning order and
 * every other observed field remain exact.
 */
final class TemporaryFiles
{
    /**
     * The comparison rule recorded with an observation using a physical filename.
     */
    public const CONTRACT = 'legacy-generated-column-temporary-file';

    /**
     * @param array<string, mixed> $observation An unchanged server observation
     * @return array<string, mixed> A comparison copy with only physical filenames normalized
     */
    public function comparable(array $observation, string $release): array
    {
        if ($release !== '5.7.44') {
            return $observation;
        }
        $error = $observation['error'] ?? null;
        if (is_array($error) && ($error[0] ?? null) === 1033 && ($error[1] ?? null) === 'HY000' && is_string($error[2] ?? null)) {
            $error[2] = $this->message($error[2]);
            $observation['error'] = $error;
        }
        $warnings = $observation['warnings'] ?? null;
        if (is_array($warnings)) {
            foreach ($warnings as $index => $warning) {
                if (is_array($warning) && ($warning[0] ?? null) === 'Error' && in_array($warning[1] ?? null, [1033, '1033'], true) && is_string($warning[2] ?? null)) {
                    $warning[2] = $this->message($warning[2]);
                    $warnings[$index] = $warning;
                }
            }
            $observation['warnings'] = $warnings;
        }
        return $observation;
    }

    /**
     * Leaves ordinary logical filenames and unrecognized diagnostic text untouched.
     */
    public function message(string $message): string
    {
        return preg_match("~\\AIncorrect information in file: '(?:/tmp/\\#sql[0-9a-f]+_[0-9a-f]+_[0-9a-f]+\\.frm|\\./[^/'\\r\\n]+/\\#sql-[0-9a-f]+_[0-9a-f]+\\.frm)?'\\z~", $message) === 1
            ? "Incorrect information in file: '{" . self::CONTRACT . "}'" : $message;
    }
}
