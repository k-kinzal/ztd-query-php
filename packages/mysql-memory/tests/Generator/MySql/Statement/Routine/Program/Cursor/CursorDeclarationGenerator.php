<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Cursor;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CursorDeclaration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration>
 */
final class CursorDeclarationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $query, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration(name: $parts[0], query: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $query,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
