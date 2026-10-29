<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RenameTo values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo>
 */
final class RenameToGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameMark>|null $mark Generated written alternative
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $inputSource = null, ?ErisGenerator $mark = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo(table: $parts[0], inputSource: $parts[1], mark: $parts[2]);
        }, Generators::tuple(
            $table,
            $inputSource ?? Generators::constant(null),
            $mark ?? new RenameMarkGenerator()
        ));
    }
}
