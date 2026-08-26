<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Throwable;

/**
 * Recognises the failures that are the reader's known limits rather than bugs.
 *
 * A fuzzer generates SQL from a grammar that describes more than this package
 * reads back: a table with no columns of its own, a statement the parser
 * abandons, a name it cannot find. Those are documented gaps, and a fuzz run
 * that stopped on each of them would never reach the cases worth finding. Any
 * other failure is a real one and must be reported.
 */
final class ParserLimitations
{
    private const KNOWN = [
        'No columns found',
        'Table name not found',
        'No statements found',
        'Could not extract table name',
        'not a CREATE TABLE',
    ];

    /**
     * Reports whether a failure is one of the reader's known limits.
     *
     * @param Throwable $failure Failure the target caught
     *
     * @return bool True when the failure is expected and the input can be passed over
     */
    public function explains(Throwable $failure): bool
    {
        foreach (self::KNOWN as $known) {
            if (str_contains($failure->getMessage(), $known)) {
                return true;
            }
        }

        return false;
    }
}
