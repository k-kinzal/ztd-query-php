<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EngineOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption>
 */
final class EngineOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $engine Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $engine, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption(engine: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $engine,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
