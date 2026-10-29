<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexUsing values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing>
 */
final class IndexUsingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm> $algorithm Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $typeKeyword The generated written alternative
     */
    public function __construct(ErisGenerator $algorithm, ?ErisGenerator $inputSource = null, ?ErisGenerator $typeKeyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing(algorithm: $parts[0], inputSource: $parts[1], typeKeyword: $parts[2]);
        }, Generators::tuple(
            $algorithm,
            $inputSource ?? Generators::constant(null),
            $typeKeyword ?? Generators::bool()
        ));
    }
}
