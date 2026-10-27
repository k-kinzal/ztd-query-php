<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OrdinaryColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn>
 */
final class OrdinaryColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> $type Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute>>|null $attributes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\References|null>|null $references Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $type, ?ErisGenerator $attributes = null, ?ErisGenerator $references = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(type: $parts[0], attributes: $parts[1], references: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $type,
            $attributes ?? Generators::constant([]),
            $references ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
