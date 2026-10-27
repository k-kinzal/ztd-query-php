<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexComment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment>
 */
final class IndexCommentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $comment, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment(comment: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $comment,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
