<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SourceNotice values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Source\SourceNotice>
 */
final class SourceNoticeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Source\SourceNotice> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Node|\SqlSemantics\Statement\Identifier\Name> $subject Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\Warning> $warning Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $subject, ErisGenerator $warning, ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Source\SourceNotice {
            return new \SqlSemantics\Statement\Source\SourceNotice(subject: $parts[0], warning: $parts[1], offset: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $subject,
            $warning,
            $offset
        ));
    }
}
