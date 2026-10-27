<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Notice values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\Notice>
 */
final class NoticeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\Notice> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\Warning> $warning Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node|\SqlSemantics\Statement\Identifier\Name|null>|null $subject Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Node|null>|null $instruction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated warning boundary
     * @param ErisGenerator<bool>|null $fromInput Whether the generated subject and boundary describe an input spelling
     */
    public function __construct(ErisGenerator $warning, ?ErisGenerator $subject = null, ?ErisGenerator $instruction = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $fromInput = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Fact\Notice {
            return new \SqlSemantics\Statement\Fact\Notice(warning: $parts[0], subject: $parts[1], instruction: $parts[2], inputSource: $parts[3], fromInput: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $warning,
            $subject ?? Generators::constant(null),
            $instruction ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $fromInput ?? Generators::constant(false)
        ));
    }
}
