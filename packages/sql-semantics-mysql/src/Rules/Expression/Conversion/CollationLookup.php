<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Resolves an expression collation in the release's preparation order.
 *
 * MySQL 5.6 and 5.7 prepare the operand before looking up the collation and
 * defer that lookup inside stored definitions. Later releases resolve a
 * nonempty name while parsing, before operand preparation. Definition-level
 * character set and collation names have their own rules.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CollationLookup
{
    /**
     * Answers a name lookup performed during parsing, before operand preparation.
     */
    public function parsed(Name $name, GrammarRelease $release): ?UnknownCollation
    {
        return !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && $name->value !== '' && Collation::forName($name->value, $release) === null ? new UnknownCollation($name->value) : null;
    }

    /**
     * Retains the operand facts and reports name lookup in the proper phase.
     */
    public function derive(Scalar|RowOperand|DeferredOperand $operand, Name $name, Derivation $derivation, Environment $environment): ScalarFact
    {
        $release = $derivation->context->profile->grammar;
        $parsed = $this->parsed($name, $release);
        $deferred = $derivation->inProgram() && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        if ($parsed !== null) {
            $derivation->report($parsed);
        }
        $fact = $derivation->scalar($operand, $environment);
        if ($parsed !== null) {
            $derivation->warn(new ParseFailure($parsed));
        } elseif (!$deferred && Collation::forName($name->value, $release) === null) {
            $derivation->report(new UnknownCollation($name->value));
        }
        return $fact;
    }
}
