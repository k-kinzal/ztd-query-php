<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Cursor;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FetchCursor values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor>
 */
final class FetchCursorGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $cursor Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $targets Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $cursor, ErisGenerator $targets, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor(cursor: $parts[0], targets: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $cursor,
            $targets,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
