<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResourceGroupNameTooLong values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong>
 */
final class ResourceGroupNameTooLongGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account/Problem/ResourceGroupNameTooLong' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $length): string => str_repeat('n', $length), Generators::choose(65, 96))))];
    }

}
