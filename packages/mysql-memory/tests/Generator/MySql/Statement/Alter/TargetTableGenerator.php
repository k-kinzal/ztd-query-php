<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TargetTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\TargetTable>
 */
final class TargetTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\TargetTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(name: $parts[0], inputSource: $parts[1], relationInformation: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ));
    }
}
