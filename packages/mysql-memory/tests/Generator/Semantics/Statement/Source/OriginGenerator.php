<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Origin values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Source\Origin>
 */
final class OriginGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Source\Origin> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Node> $node Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<int> $length Generated constructor operand
     */
    public function __construct(ErisGenerator $node, ErisGenerator $offset, ErisGenerator $length)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Source\Origin {
            return new \SqlSemantics\Statement\Source\Origin(node: $parts[0], offset: $parts[1], length: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $node,
            $offset,
            $length
        ));
    }
}
