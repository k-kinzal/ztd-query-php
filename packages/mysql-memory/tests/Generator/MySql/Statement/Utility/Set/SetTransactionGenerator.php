<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;

/**
 * Composes SetTransaction operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<SetTransaction>
 */
final class SetTransactionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetTransaction> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null>|null $scopes Scopes allowed in the consuming sequence
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $scopes = null)
    {
        $levels = \SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel::cases();
        $modes = \SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::cases();
        $pairs = [];
        foreach ($levels as $level) {
            $pairs[] = [$level];
            foreach ($modes as $mode) {
                $pairs[] = [$level, $mode];
                $pairs[] = [$mode, $level];
            }
        }
        foreach ($modes as $mode) {
            $pairs[] = [$mode];
        }
        $allowed = [null, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session];
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $allowed[] = \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist;
            $allowed[] = \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::PersistOnly;
        }
        $this->delegate = Generators::map(static function (array $data) use ($release): SetTransaction {
            [$characteristics, $scope] = $data;
            if (!is_array($characteristics) || ($scope !== null && !$scope instanceof \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope)) {
                throw new LogicException('SET TRANSACTION generators provide characteristics and a scope.');
            }
            return new SetTransaction($characteristics, $scope, profile: new LanguageProfile($release));
        }, Generators::tuple(Generators::elements($pairs), $scopes ?? Generators::elements($allowed)));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<SetTransaction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SetTransaction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
