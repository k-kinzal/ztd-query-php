<?php

declare(strict_types=1);

namespace Tests\Generator;

use DomainException;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Signatures;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Selects a language for retained query operands without promoting them to read statements.
 * Only declared language requirements participate; no database outcome selects a profile.
 */
final class QueryProfiles
{
    /**
     * @throws DomainException When the generated operands have no common language
     */
    public function select(Query $query): LanguageProfile
    {
        $generation = 0;
        $transition = 0;
        $modes = 0;
        $releases = ReleaseDomain::ALL;
        $calls = [];
        $pending = [$query];
        $forms = new Generations();
        $languages = new ReleaseDomain();
        $lexical = new LexicalModes();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value)) {
                $generation |= $forms->requirement($value);
                $transition |= $forms->transition($value);
                $modes |= $lexical->requirement($value);
                $releases &= $languages->of($value);
                if ($value instanceof FunctionCall || $value instanceof DeferredCall) {
                    $calls[] = $value;
                }
                if ($value instanceof Node) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
        $releases &= match ($generation) {
            0 => ReleaseDomain::ALL, 1 => 3, 2 => 508, default => 0
        };
        $releases &= match ($transition) {
            0 => ReleaseDomain::ALL, 1 => 1, 2 => 510, default => 0
        };
        foreach ((new Profiles())->candidates($query) as $profile) {
            $combined = $modes | $lexical->requirement($profile);
            if (($combined & 3) !== 3 && ($combined & 12) !== 12 && ($releases & $languages->bit($profile->grammar)) !== 0
                && (new Signatures())->compatible($calls, $generation, $transition, $profile, $releases)) {
                return $profile;
            }
        }
        throw new DomainException('No language profile admits the generated query operands.');
    }
}
