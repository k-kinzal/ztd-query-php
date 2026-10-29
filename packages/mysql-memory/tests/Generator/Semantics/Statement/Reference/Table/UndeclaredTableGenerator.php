<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UndeclaredTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\UndeclaredTable>
 */
final class UndeclaredTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\UndeclaredTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Missing\UndeclaredRelation> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\UndeclaredTable {
            return new \SqlSemantics\Statement\Reference\Table\UndeclaredTable(missing: $parts[0]);
        }, Generators::tuple(
            $missing
        ));
    }
}
