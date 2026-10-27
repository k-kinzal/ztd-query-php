<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EmptyObjectName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName>
 */
final class EmptyObjectNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind> $kind Generated constructor operand
     */
    public function __construct(ErisGenerator $kind)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName {
            return new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName(kind: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $kind
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name\\EmptyObjectName' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new EmptyObjectNameGenerator(
            kind: ObjectNameKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table])
        )];
    }

}
