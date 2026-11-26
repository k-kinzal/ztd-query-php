<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;

/**
 * Resolves the supplied positions against the owning target layout.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class InsertTargetLowering
{
    /**
     * Resolves explicitly named or implicit writable positions in the same declaration.
     * @return list<ColumnReference>
     */
    public function lower(SourceBinding $binding, InsertInto $into, bool $defaults): array
    {
        if ($defaults) {
            Check::input($into->columns === [], 'DEFAULT VALUES does not admit a written column list.');

            return [];
        }
        if ($into->columns === []) {
            return $binding->implicitWrites();
        }

        $writes = [];
        foreach ($into->columns as $name) {
            $writes[] = new ColumnReference($binding->write($name));
        }

        return $writes;
    }
}
