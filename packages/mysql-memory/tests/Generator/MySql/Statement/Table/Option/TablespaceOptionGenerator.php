<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TablespaceOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption>
 */
final class TablespaceOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $tablespace Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     */
    public function __construct(ErisGenerator $tablespace, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption(tablespace: $parts[0], inputSource: $parts[1], equals: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $tablespace,
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool()
        ));
    }
}
