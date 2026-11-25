<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;

/**
 * Resolves the effective visibility of an admitted native B-tree declaration.
 *
 * @visibility SqlSemantics
 */
final class IndexOptionsLowering
{
    /**
     * Validates the index's syntax choices and returns the effective visibility.
     */
    public function visible(ParsedCreateIndex $statement): bool
    {
        Check::input(
            $statement->kind === IndexKind::Index || $statement->kind === IndexKind::Unique,
            'CREATE INDEX requires an ordinary or unique native B-tree role.'
        );
        Check::input(
            ($statement->algorithm === null || $statement->algorithm === IndexAlgorithm::Btree) && $statement->alterOptions === [],
            'CREATE INDEX requires the ordinary native B-tree storage and alteration policy.'
        );
        $visible = true;
        foreach ($statement->options as $option) {
            if ($option instanceof IndexVisibility) {
                $visible = $option->visible;
            } else {
                Check::input(
                    $option instanceof IndexUsing && $option->algorithm === IndexAlgorithm::Btree,
                    'CREATE INDEX requires checked B-tree and visibility options.'
                );
            }
        }

        return $visible;
    }
}
