<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CommentOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption>
 */
final class CommentOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     */
    public function __construct(ErisGenerator $comment, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption(comment: $parts[0], inputSource: $parts[1], equals: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $comment,
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool()
        ));
    }
}
