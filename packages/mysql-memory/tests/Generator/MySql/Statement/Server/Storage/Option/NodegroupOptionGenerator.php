<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NodegroupOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption>
 */
final class NodegroupOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $group Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     */
    public function __construct(ErisGenerator $group, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption(group: $parts[0], inputSource: $parts[1], equals: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $group,
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool()
        ));
    }
}
