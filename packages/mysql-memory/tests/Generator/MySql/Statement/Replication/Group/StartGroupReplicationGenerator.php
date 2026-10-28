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

    /**
     * Generates complete credential lists and their SQL condition.
     *
     * @return non-empty-list<ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication, int}>>
     */
    public static function conditions(): array
    {
        $password = new CredentialOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $length): string => str_repeat('p', $length), Generators::choose(1, 32))));
        $user = new CredentialOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'user' . $value, Generators::choose(0, 1000))));
        return [
            Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication $request): array => [$request, 3971], new self(\Tests\Generator\Operands::tuple($password))),
            Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication $request): array => [$request, 3970], new self(\Tests\Generator\Operands::tuple($user, new CredentialOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::constant(''))), $password))),
            Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication $request): array => [$request, 3972], new self(\Tests\Generator\Operands::tuple($user, new CredentialOptionGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $length): string => str_repeat('p', $length), Generators::choose(33, 64))))))),
        ];
    }

    /**
     * Composes related procedure definitions and calls with the credential condition's phase.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function stored(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static fn (array $parts): array => self::storedInput($parts, $release), \Tests\Generator\Operands::oneOf(self::conditions()));
    }

    /**
     * Keeps an execution-only credential condition deferred inside its generated routine.
     *
     * @param array{\SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication, int} $parts The request and its condition
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}
     */
    public static function storedInput(array $parts, \SqlSemantics\Contract\GrammarRelease $release): array
    {
        [$request, $code] = $parts;
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('group_recovery'));
        $body = $code === 3971 ? new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart($request->options) : $request;
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure($name, new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $body);
        $call = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall($name);
        return [\SqlSemantics\Facade\Semantics::fromStatement($context, $definition), \SqlSemantics\Facade\Semantics::fromStatement($context, $call), $code];
    }

}
