<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserVariableEntry values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry>
 */
final class UserVariableEntryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $domain Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation> $collation Generated constructor operand
     * @param ErisGenerator<bool|null>|null $binaryString Generated constructor operand
     */
    public function __construct(ErisGenerator $domain, ErisGenerator $collation, ?ErisGenerator $binaryString = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(domain: $parts[0], collation: $parts[1], binaryString: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $domain,
            $collation,
            $binaryString ?? Generators::constant(null)
        ));
    }
    /**
     * Generates an assigned integer's value domain and connection collation.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry>
     */
    public static function integers(): ErisGenerator
    {
        return new self(new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
            \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
            \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong]),
            Generators::constant(21),
            coercibility: \Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Numeric]),
        ), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
    }

}
