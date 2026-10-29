<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedXaIdentifier values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier>
 */
final class RefusedXaIdentifierGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     * @param ErisGenerator<bool>|null $following Generated constructor operand
     */
    public function __construct(ErisGenerator $offset, ?ErisGenerator $following = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier(offset: $parts[0], following: $parts[1]);
        }, Generators::tuple(
            $offset,
            $following ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\RefusedXaIdentifier' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedXaIdentifierGenerator(
            offset: Generators::constant(20),
            following: Generators::constant(false)
        )];
    }

}
