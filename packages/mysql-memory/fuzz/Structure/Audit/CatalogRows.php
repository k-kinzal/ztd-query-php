<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Fuzz\Target\Observer;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Statement\Scalar;

/**
 * Character catalogs have no ordering clause; compare their complete row multisets.
 * Diagnostic sequences and other statement results retain their original order.
 */
final class CatalogRows
{
    /**
     * @param array<string, mixed> $observation The complete native or memory observation
     * @return array<string, mixed> The same observation with unordered catalog rows sorted
     */
    public function comparable(Statement|Scalar $node, array $observation): array
    {
        if (!$node instanceof ShowCharacterSet && !$node instanceof ShowCollation) {
            return $observation;
        }
        $results = $observation['results'] ?? null;
        if (!is_array($results)) {
            return $observation;
        }
        foreach ($results as $index => $result) {
            if (is_array($result) && is_array($result['rows'] ?? null)) {
                $result['rows'] = (new Observer())->bag($result['rows']);
                $results[$index] = $result;
            }
        }
        $observation['results'] = $results;

        return $observation;
    }
}
