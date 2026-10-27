<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CharsetConflict values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict>
 */
final class CharsetConflictGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset> $earlier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset> $later Generated constructor operand
     */
    public function __construct(ErisGenerator $earlier, ErisGenerator $later)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict(earlier: $parts[0], later: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $earlier,
            $later
        ));
    }
    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server/Database/Problem/CharsetConflict' => [\SqlSemantics\Contract\GrammarRelease::MySql847, \Tests\Generator\Operands::bind(Generators::elements([['latin1', 1, 'utf8mb4', 4], ['ascii', 1, 'utf8mb3', 3], ['utf8mb4', 4, 'latin1', 1]]), static fn (array $parts): self => new self(
            new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(Generators::constant($parts[0]), Generators::constant($parts[1])),
            new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(Generators::constant($parts[2]), Generators::constant($parts[3])),
        ))];
    }

}
