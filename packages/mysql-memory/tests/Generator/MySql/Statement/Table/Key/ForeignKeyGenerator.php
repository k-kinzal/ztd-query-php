<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ForeignKey values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey>
 */
final class ForeignKeyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\References> $references Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName|null>|null $constraint Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $columns, ErisGenerator $references, ?ErisGenerator $name = null, ?ErisGenerator $constraint = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey(columns: $parts[0], references: $parts[1], name: $parts[2], constraint: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $columns,
            $references,
            $name ?? Generators::constant(null),
            $constraint ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
