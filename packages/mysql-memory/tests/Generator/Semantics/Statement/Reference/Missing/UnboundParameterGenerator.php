<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnboundParameter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\UnboundParameter>
 */
final class UnboundParameterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\UnboundParameter> */
    use Composition;

    /**
     * @param ErisGenerator<string> $marker Generated constructor operand
     */
    public function __construct(ErisGenerator $marker)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Missing\UnboundParameter {
            return new \SqlSemantics\Statement\Reference\Missing\UnboundParameter(marker: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $marker
        ));
    }
}
