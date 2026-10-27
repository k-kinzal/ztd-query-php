<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WaitOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption>
 */
final class WaitOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $wait Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $wait, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption(wait: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $wait,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
