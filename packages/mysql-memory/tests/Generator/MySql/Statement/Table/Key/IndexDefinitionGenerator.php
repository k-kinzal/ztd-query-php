<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition>
 */
final class IndexDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart>> $parts Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm|null>|null $algorithm Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName|null>|null $constraint Generated constructor operand
     * @param ErisGenerator<bool>|null $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $parts, ?ErisGenerator $name = null, ?ErisGenerator $algorithm = null, ?ErisGenerator $options = null, ?ErisGenerator $constraint = null, ?ErisGenerator $keyword = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition(kind: $parts[0], parts: $parts[1], name: $parts[2], algorithm: $parts[3], options: $parts[4], constraint: $parts[5], keyword: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $parts,
            $name ?? Generators::constant(null),
            $algorithm ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $constraint ?? Generators::constant(null),
            $keyword ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
