<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Level;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ObjectLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel>
 */
final class ObjectLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(name: $parts[0], inputSource: $parts[1], relationInformation: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ));
    }
}
