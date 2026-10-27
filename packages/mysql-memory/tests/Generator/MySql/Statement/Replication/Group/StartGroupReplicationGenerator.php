<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Group;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StartGroupReplication values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication>
 */
final class StartGroupReplicationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication(options: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication>>
     */
    public static function forms(): iterable
    {
        yield 'StartGroupReplication/278' => new StartGroupReplicationGenerator(
            options: Generators::constant([])
        );
        yield 'StartGroupReplication/279' => new StartGroupReplicationGenerator(
            options: \Tests\Generator\Operands::tuple(
                new CredentialOptionGenerator(
                    credential: CredentialGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User]),
                    value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    )
                )
            )
        );
    }

}
