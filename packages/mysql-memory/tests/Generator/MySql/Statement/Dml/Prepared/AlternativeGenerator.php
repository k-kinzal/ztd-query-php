<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Prepared;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Alternative values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative>
 */
final class AlternativeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation> $interpretation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Statement|\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal> $statement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\SourceMap>|null $sources Generated constructor operand
     */
    public function __construct(ErisGenerator $interpretation, ErisGenerator $statement, ?ErisGenerator $sources = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative(interpretation: $parts[0], statement: $parts[1], sources: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $interpretation,
            $statement,
            $sources ?? Generators::constant(new \SqlSemantics\Statement\Source\SourceMap())
        ));
    }
}
