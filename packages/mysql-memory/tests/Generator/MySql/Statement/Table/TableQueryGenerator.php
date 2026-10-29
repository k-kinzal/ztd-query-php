<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableQuery>
 */
final class TableQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\TableQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling|null>|null $duplicate Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $as The generated written alternative
     */
    public function __construct(ErisGenerator $query, ?ErisGenerator $duplicate = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $as = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\TableQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Table\TableQuery(query: $parts[0], duplicate: $parts[1], inputSource: $parts[2], as: $parts[3]);
        }, Generators::tuple(
            $query,
            $duplicate ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $as ?? Generators::bool()
        ));
    }
}
