<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity;

/**
 * Resolves key-part counts that cannot describe an ordinary index of the chosen kind.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Shapes
{
    /**
     * @param list<KeyPart> $parts Complete ordered index operands
     */
    public function problem(IndexKind $kind, array $parts): SpatialKeyArity|FunctionalIndexKind|null
    {
        if ($kind === IndexKind::Spatial && count($parts) > 1) {
            return new SpatialKeyArity($parts[0]);
        }
        if (in_array($kind, [IndexKind::Primary, IndexKind::FullText, IndexKind::Spatial], true)) {
            foreach ($parts as $part) {
                if (!$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart) {
                    return new FunctionalIndexKind($kind, $part);
                }
            }
        }
        return null;
    }
}
