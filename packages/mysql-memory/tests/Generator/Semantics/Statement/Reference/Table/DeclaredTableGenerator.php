<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeclaredTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\DeclaredTable>
 */
final class DeclaredTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\DeclaredTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\Table> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $schema Resolved namespace
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $schema = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\DeclaredTable {
            return new \SqlSemantics\Statement\Reference\Table\DeclaredTable(table: $parts[0], schema: $parts[1]);
        }, Generators::tuple(
            $table,
            $schema ?? Generators::constant(null)
        ));
    }
}
