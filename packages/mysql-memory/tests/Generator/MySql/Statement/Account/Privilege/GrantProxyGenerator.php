<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrantProxy values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy>
 */
final class GrantProxyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $proxied Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\Grantee|\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>> $grantees Generated constructor operand
     * @param ErisGenerator<bool>|null $withGrantOption Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $proxied, ErisGenerator $grantees, ?ErisGenerator $withGrantOption = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy(proxied: $parts[0], grantees: $parts[1], withGrantOption: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $proxied,
            $grantees,
            $withGrantOption ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy>>
     */
    public static function forms(): iterable
    {
        yield 'GrantProxy/400' => new GrantProxyGenerator(
            proxied: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            withGrantOption: Generators::constant(false)
        );
    }

}
