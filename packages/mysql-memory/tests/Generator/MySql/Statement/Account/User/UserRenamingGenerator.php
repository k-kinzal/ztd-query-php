<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates independently written source and destination account operands.
 *
 * @implements ErisGenerator<UserRenaming>
 */
final class UserRenamingGenerator implements ErisGenerator
{
    /** @var ErisGenerator<UserRenaming> */
    private ErisGenerator $delegate;

    /**
     * Composes both operands without requiring the runtime accounts to exist.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (array $parts): UserRenaming => new UserRenaming($parts[0], $parts[1]), Generators::tuple(new AccountGenerator(), new AccountGenerator()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<UserRenaming>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<UserRenaming>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
