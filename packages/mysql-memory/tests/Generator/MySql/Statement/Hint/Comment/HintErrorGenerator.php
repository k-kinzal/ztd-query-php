<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Comment;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HintError values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError>
 */
final class HintErrorGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure> $failure Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<bool>|null $closing Generated constructor operand
     * @param ErisGenerator<bool>|null $leading Whether the warning precedes other input notices
     */
    public function __construct(ErisGenerator $failure, ErisGenerator $offset, ?ErisGenerator $closing = null, ?ErisGenerator $leading = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError(failure: $parts[0], offset: $parts[1], closing: $parts[2], leading: $parts[3]);
        }, Generators::tuple(
            $failure,
            $offset,
            $closing ?? Generators::constant(false),
            $leading ?? Generators::constant(false)
        ));
    }
}
