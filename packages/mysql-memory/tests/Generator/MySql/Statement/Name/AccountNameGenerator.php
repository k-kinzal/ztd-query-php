<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\Quoted\NotationGenerator;
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
        $this->delegate = self::fromOperands(new NameGenerator($users, Generators::constant(null)), $hosts);
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
        return Generators::bind(Generators::tuple($user, $host), static function (array $parts): ErisGenerator {
            [$user, $host] = $parts;
            $quotation = static fn (?Name $name): ErisGenerator => $name === null || !in_array($name->quote, [null, \SqlSemantics\Statement\Identifier\NameQuote::SingleQuote], true) ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), NotationGenerator::forValue($name->value));

            return Generators::map(static fn (array $notations): AccountName => new AccountName($user, $host, userQuotation: $notations[0], hostQuotation: $notations[1]), Generators::tuple($quotation($user), $quotation($host)));
        });
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
            $account = new AccountName(new Name($user), $host === null ? null : new Name($host), userQuotation: $quoted ? new Notation() : null, hostQuotation: $host !== null && $quoted ? new Notation() : null);
            return [$account, $kind === 'separator' ? 1105 : 1470];
        }, Generators::tuple(Generators::elements('user', 'host', 'separator'), Generators::choose(1, 9), Generators::elements('u', '猫'), Generators::bool()));
    }

}
