<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Domain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>
 */
final class DomainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field> $field Generated constructor operand
     * @param ErisGenerator<int>|null $length Generated constructor operand
     * @param ErisGenerator<int>|null $decimals Generated constructor operand
     * @param ErisGenerator<bool>|null $unsigned Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation|null>|null $collation Generated constructor operand
     * @param ErisGenerator<list<string>>|null $members Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility>|null $coercibility Generated constructor operand
     * @param ErisGenerator<int|null>|null $display Generated constructor operand
     * @param ErisGenerator<int|null>|null $capacity Generated constructor operand
     * @param ErisGenerator<int|null>|null $displayDecimals Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field|null>|null $storageField Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $field, ?ErisGenerator $length = null, ?ErisGenerator $decimals = null, ?ErisGenerator $unsigned = null, ?ErisGenerator $collation = null, ?ErisGenerator $members = null, ?ErisGenerator $coercibility = null, ?ErisGenerator $display = null, ?ErisGenerator $capacity = null, ?ErisGenerator $displayDecimals = null, ?ErisGenerator $storageField = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain(kind: $parts[0], field: $parts[1], length: $parts[2], decimals: $parts[3], unsigned: $parts[4], collation: $parts[5], members: $parts[6], coercibility: $parts[7], display: $parts[8], capacity: $parts[9], displayDecimals: $parts[10], storageField: $parts[11]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $field,
            $length ?? Generators::constant(0),
            $decimals ?? Generators::constant(0),
            $unsigned ?? Generators::constant(false),
            $collation ?? Generators::constant(null),
            $members ?? Generators::constant([]),
            $coercibility ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Implicit),
            $display ?? Generators::constant(null),
            $capacity ?? Generators::constant(null),
            $displayDecimals ?? Generators::constant(null),
            $storageField ?? Generators::constant(null)
        ));
    }
}
