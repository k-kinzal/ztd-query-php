<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GeneratedColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn>
 */
final class GeneratedColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage|null>|null $storage Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute>>|null $attributes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\References|null>|null $references Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $type, ErisGenerator $expression, ?ErisGenerator $collation = null, ?ErisGenerator $storage = null, ?ErisGenerator $attributes = null, ?ErisGenerator $references = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn(type: $parts[0], expression: $parts[1], collation: $parts[2], storage: $parts[3], attributes: $parts[4], references: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $type,
            $expression,
            $collation ?? Generators::constant(null),
            $storage ?? Generators::constant(null),
            $attributes ?? Generators::constant([]),
            $references ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
