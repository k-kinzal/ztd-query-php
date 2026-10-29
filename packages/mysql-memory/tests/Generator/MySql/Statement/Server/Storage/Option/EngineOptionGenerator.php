<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EngineOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption>
 */
final class EngineOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $engine Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     * @param ErisGenerator<bool>|null $storageKeyword Generated constructor operand
     */
    public function __construct(ErisGenerator $engine, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null, ?ErisGenerator $storageKeyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption(engine: $parts[0], inputSource: $parts[1], operator: $parts[2], storageKeyword: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $engine,
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(),
            $storageKeyword ?? Generators::bool()
        ));
    }
}
