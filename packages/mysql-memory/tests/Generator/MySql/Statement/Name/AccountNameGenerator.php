<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Composes account operands independently of an installed account catalog.
 *
 * @implements ErisGenerator<AccountName>
 */
final class AccountNameGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AccountName> */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<string>|null $users User operands within the account domain
     * @param ErisGenerator<Name|null>|null $hosts Optional host operands
     */
    public function __construct(?ErisGenerator $users = null, ?ErisGenerator $hosts = null)
    {
        $users ??= Generators::map(static fn (int $number): string => 'u' . $number, Generators::choose(0, 999999));
        $hosts ??= Generators::oneOf(Generators::constant(null), new NameGenerator(Generators::elements('%', 'localhost', '10.0.%'), Generators::constant(null)));
        $this->delegate = \Tests\Generator\Operands::bind(new NameGenerator($users, Generators::constant(null)), static fn (Name $user): ErisGenerator => Generators::map(static fn (?Name $host): AccountName => new AccountName($user, $host), $hosts));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<AccountName>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AccountName>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Name> $user The generated semantic operand
     * @param ErisGenerator<Name|null> $host The generated semantic operand
     * @return ErisGenerator<AccountName>
     */
    public static function fromOperands(ErisGenerator $user, ErisGenerator $host): ErisGenerator
    {
        return Generators::map(static fn (array $parts): AccountName => new AccountName(user: $parts[0], host: $parts[1]), \Tests\Generator\Operands::tuple($user, $host));
    }

}
