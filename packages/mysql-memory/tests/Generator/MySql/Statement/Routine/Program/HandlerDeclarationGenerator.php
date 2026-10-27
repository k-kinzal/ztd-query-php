<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HandlerDeclaration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration>
 */
final class HandlerDeclarationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction> $action Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition>> $conditions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node> $statement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $action, ErisGenerator $conditions, ErisGenerator $statement, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration(action: $parts[0], conditions: $parts[1], statement: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $action,
            $conditions,
            $statement,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
