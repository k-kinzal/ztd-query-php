<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropElement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement>
 */
final class DropElementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior|null>|null $behavior Generated constructor operand
     * @param ErisGenerator<bool|null>|null $columnWritten Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\IndexWord|null>|null $keyword Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $name = null, ?ErisGenerator $behavior = null, ?ErisGenerator $columnWritten = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $keyword = null)
    {
        $this->delegate = Generators::bind(Generators::tuple(
            $kind,
            $name ?? Generators::constant(null),
            $behavior ?? Generators::constant(null),
            $columnWritten ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($keyword): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $selected): \SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement => new \SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement(kind: $parts[0], name: $parts[1], behavior: $parts[2], columnWritten: $parts[3], inputSource: $parts[4], keyword: $selected), $keyword ?? ($parts[0] === \SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::Index ? new \Tests\Generator\MySql\Statement\Table\IndexWordGenerator() : Generators::constant(null)));
        });
    }
}
