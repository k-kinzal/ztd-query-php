<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes KeyMethod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod>
 */
final class KeyMethodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $linear Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null> $algorithm Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $linear, ErisGenerator $algorithm, ErisGenerator $columns, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod(linear: $parts[0], algorithm: $parts[1], columns: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $linear,
            $algorithm,
            $columns,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
