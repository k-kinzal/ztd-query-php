<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Key;

use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;

/**
 * Identifies index directions whose ignored interpretation belongs to MySQL 5.x.
 *
 * MySQL 8.0 and later refuse explicit directions on FULLTEXT, SPATIAL and HASH
 * indexes before opening referenced tables. Both 5.6 and 5.7 accept them. The
 * release constraint composes with other retained operands, including modern
 * functional keys, without resolving a table or choosing an execution engine.
 * Verified through native SQL on 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IndexOrdering
{
    /**
     * @param list<KeyPart> $parts The complete key parts
     * @param list<IndexOption> $options The index options in written order
     */
    public function legacy(IndexKind $kind, array $parts, ?IndexAlgorithm $algorithm = null, array $options = []): bool
    {
        return $this->unordered($kind, $algorithm, $options)
            && array_filter($parts, static fn (KeyPart $part): bool => ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart || $part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart || $part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart || $part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart) && $part->direction !== null) !== [];
    }

    /**
     * @param list<IndexOption> $options The trailing algorithm overrides in written order
     */
    public function unordered(IndexKind $kind, ?IndexAlgorithm $algorithm, array $options): bool
    {
        foreach ($options as $option) {
            if ($option instanceof IndexUsing) {
                $algorithm = $option->algorithm;
            }
        }
        return $algorithm === IndexAlgorithm::Hash || $kind === IndexKind::FullText || $kind === IndexKind::Spatial;
    }

}
