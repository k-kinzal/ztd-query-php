<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RevokeProxy values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy>
 */
final class RevokeProxyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $proxied Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>> $users Generated constructor operand
     * @param ErisGenerator<bool>|null $ignoreUnknownUser Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $proxied, ErisGenerator $users, ?ErisGenerator $ignoreUnknownUser = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy(ifExists: $parts[0], proxied: $parts[1], users: $parts[2], ignoreUnknownUser: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $proxied,
            $users,
            $ignoreUnknownUser ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy>>
     */
    public static function forms(): iterable
    {
        yield 'RevokeProxy/398' => new RevokeProxyGenerator(
            ifExists: Generators::constant(false),
            proxied: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            users: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )
            ),
            ignoreUnknownUser: Generators::constant(false)
        );
    }

}
