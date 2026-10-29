<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConditionalTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\ConditionalTable>
 */
final class ConditionalTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\ConditionalTable> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Table>> $candidates Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Reference\Missing\UndeclaredRelation> $missing Generated constructor operand
     */
    public function __construct(ErisGenerator $candidates, ErisGenerator $missing)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\ConditionalTable {
            return new \SqlSemantics\Statement\Reference\Table\ConditionalTable(candidates: $parts[0], missing: $parts[1]);
        }, Generators::tuple(
            $candidates,
            $missing
        ));
    }
}
