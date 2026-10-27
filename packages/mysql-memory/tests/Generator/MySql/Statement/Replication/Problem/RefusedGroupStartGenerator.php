<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedGroupStart values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart>
 */
final class RefusedGroupStartGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart> $request Generated constructor operand
     */
    public function __construct(ErisGenerator $request)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart(request: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $request
        ));
    }
    /**
     * Generates the complete semantic condition consumed by input failure handling.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Replication/Problem/RefusedGroupStart' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(
            new \Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredGroupStartGenerator(Generators::vector(
                1,
                new \Tests\Generator\MySql\Statement\Replication\Group\CredentialOptionGenerator(
                    \Tests\Generator\MySql\Statement\Replication\Group\CredentialGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password]),
                    new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $suffix): string => 'password_' . $suffix, Generators::choose(0, 100))),
                ),
            )),
        )];
    }

}
