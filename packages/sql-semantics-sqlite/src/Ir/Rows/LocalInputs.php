<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Enumerates the local uses in an ordered input layout.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class LocalInputs
{
    /**
     * Collects the local definition at each occurrence.
     * @param list<Join> $joins
     * @return list<LocalSource>
     */
    public function occurrences(TableSource|LocalSource|null $source, array $joins): array
    {
        $local = $source instanceof LocalSource ? [$source] : [];
        foreach ($joins as $join) {
            if ($join->source instanceof LocalSource) {
                $local[] = $join->source;
            }
        }

        return $local;
    }

    /**
     * Validates sharing and returns the distinct definition that must be rendered.
     * @param list<Join> $joins
     */
    public function checked(TableSource|LocalSource|null $source, array $joins): ?LocalSource
    {
        $uses = $this->occurrences($source, $joins);
        $local = $uses[0] ?? null;
        foreach ($uses as $use) {
            Check::input($use === $local, 'A joined SELECT must share one local definition.');
        }
        Check::input(!($local?->query instanceof RecursiveDefinition) || $joins === [], 'A recursive local definition requires a SELECT without joins.');

        return $local;
    }
}
