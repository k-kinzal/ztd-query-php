<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NameTooLong values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong>
 */
final class NameTooLongGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind|null>|null $kind Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $kind = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong {
            return new \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong(name: $parts[0], kind: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $kind ?? Generators::constant(null)
        ));
    }
    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Name/Problem/NameTooLong' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $length): string => str_repeat('n', $length), Generators::choose(65, 96))), new \Tests\Generator\MySql\Statement\Name\ObjectNameKindGenerator())];
    }

}
