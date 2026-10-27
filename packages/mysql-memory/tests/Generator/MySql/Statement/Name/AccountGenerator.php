<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * Selects a written account identity or the current account reference.
 *
 * @implements ErisGenerator<Account>
 */
final class AccountGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Account> */
    private ErisGenerator $delegate;

    /**
     * Composes each account alternative through Eris.
     *
     * @param non-empty-list<ErisGenerator<Account>>|null $alternatives Account spellings valid for the consuming SQL role
     */
    public function __construct(?array $alternatives = null)
    {
        $this->delegate = Generators::oneOf(...($alternatives ?? [new AccountNameGenerator(), new CurrentUserGenerator()]));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Account>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Account>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
