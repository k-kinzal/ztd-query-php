<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column\Generation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredGeneratedColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn>
 */
final class DeferredGeneratedColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage|null>|null $storage Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute>>|null $attributes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\References|null>|null $references Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $generatedAlways Generated constructor operand
     */
    public function __construct(ErisGenerator $type, ErisGenerator $expression, ?ErisGenerator $collation = null, ?ErisGenerator $storage = null, ?ErisGenerator $attributes = null, ?ErisGenerator $references = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $generatedAlways = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn(type: $parts[0], expression: $parts[1], collation: $parts[2], storage: $parts[3], attributes: $parts[4], references: $parts[5], inputSource: $parts[6], generatedAlways: $parts[7]);
        }, Generators::tuple(
            $type,
            $expression,
            $collation ?? Generators::constant(null),
            $storage ?? Generators::constant(null),
            $attributes ?? Generators::constant([]),
            $references ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $generatedAlways ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)
        ));
    }
}
