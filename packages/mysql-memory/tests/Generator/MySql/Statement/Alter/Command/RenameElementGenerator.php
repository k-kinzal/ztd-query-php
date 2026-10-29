<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RenameElement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement>
 */
final class RenameElementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName> $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName> $to Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\IndexWord|null>|null $keyword Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $from, ErisGenerator $to, ?ErisGenerator $inputSource = null, ?ErisGenerator $keyword = null)
    {
        $this->delegate = Generators::bind(Generators::tuple(
            $kind,
            $from,
            $to,
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($keyword): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $selected): \SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement => new \SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement(kind: $parts[0], from: $parts[1], to: $parts[2], inputSource: $parts[3], keyword: $selected), $keyword ?? ($parts[0] === \SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::Index ? new \Tests\Generator\MySql\Statement\Table\IndexWordGenerator() : Generators::constant(null)));
        });
    }
}
