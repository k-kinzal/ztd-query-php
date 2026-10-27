<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnionOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption>
 */
final class UnionOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $tables, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption(tables: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $tables,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
