<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LeadingUnion values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion>
 */
final class LeadingUnionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier|null> $quantifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Select|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $left, ErisGenerator $quantifier, ErisGenerator $right, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion(left: $parts[0], quantifier: $parts[1], right: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $left,
            $quantifier,
            $right,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
