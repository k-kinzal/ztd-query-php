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
     * @param ErisGenerator<string>|null $users Decoded user operands, including values outside the account domain
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
     * @param ErisGenerator<bool>|null $userString Generated string notation for an unquoted decoded name
     * @param ErisGenerator<bool>|null $hostString Generated string notation for an existing host
     * @return ErisGenerator<AccountName>
     */
    public static function fromOperands(ErisGenerator $user, ErisGenerator $host, ?ErisGenerator $userString = null, ?ErisGenerator $hostString = null): ErisGenerator
    {
        return Generators::map(static fn (array $parts): AccountName => new AccountName(user: $parts[0], host: $parts[1], userString: $parts[0]->quote === null && $parts[2], hostString: $parts[1] !== null && $parts[1]->quote === null && $parts[3]), \Tests\Generator\Operands::tuple($user, $host, $userString ?? Generators::bool(), $hostString ?? Generators::bool()));
    }

    /**
     * Composes invalid user-character and host-byte domains with retained notation.
     *
     * @return ErisGenerator<array{AccountName, int}>
     */
    public static function refusedOperands(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        [$users, $hosts] = (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->limits($release);
        return Generators::map(static function (array $parts) use ($users, $hosts): array {
            [$kind, $excess, $unit, $quoted] = $parts;
            $user = $kind === 'user' ? str_repeat($unit, $users + $excess) : 'account_subject';
            $host = match ($kind) {
                'user' => null,
                'host' => str_repeat($unit, intdiv($hosts, strlen($unit)) + $excess),
                default => 'bad@host',
            };
            $account = new AccountName(new Name($user), $host === null ? null : new Name($host), userString: $quoted, hostString: $host !== null && $quoted);
            return [$account, $kind === 'separator' ? 1105 : 1470];
        }, \Tests\Generator\Operands::tuple(Generators::elements('user', 'host', 'separator'), Generators::choose(1, 9), Generators::elements('u', '猫'), Generators::bool()));
    }

}
