<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem\User;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use Tests\Generator\MySql\Statement\Account\User\UserRenamingGenerator;

/**
 * Generates an ordered rename list with an intrinsic current-account contradiction.
 *
 * @implements ErisGenerator<RefusedRenameUser>
 */
final class RefusedRenameUserGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedRenameUser> */
    private ErisGenerator $delegate;

    /**
     * Shrinks preceding pairs while preserving the contradictory final pair.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (array $pairs): RefusedRenameUser => new RefusedRenameUser([...$pairs, new UserRenaming(new CurrentUser(), new CurrentUser())]), \Tests\Generator\Operands::seq(new UserRenamingGenerator()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RefusedRenameUser>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedRenameUser>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<list<UserRenaming>> $renamings The generated semantic operand
     * @return ErisGenerator<RefusedRenameUser>
     */
    public static function fromOperands(ErisGenerator $renamings): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RefusedRenameUser => new RefusedRenameUser(renamings: $parts[0]), \Tests\Generator\Operands::tuple($renamings));
    }


}
