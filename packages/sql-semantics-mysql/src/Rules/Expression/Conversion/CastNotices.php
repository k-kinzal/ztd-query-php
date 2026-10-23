<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Derives target notices shared by ordinary and deferred casts.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CastNotices
{
    /**
     * Reports target spelling notices and an unknown character set.
     */
    public function derive(CastTarget $target, Derivation $derivation): void
    {
        if ($target->kind === CastKind::NationalChar) {
            Deprecation::raise(Deprecated::National, $derivation);
        }
        if ($target->charset?->charset !== null) {
            $this->charset($target->charset->charset->value, $derivation);
        }
    }

    /**
     * Reports a character bound that overflows its resolved character set.
     * Packet limits remain execution state; this is the target's byte capacity.
     */
    public function capacity(CastTarget $target, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain $domain, Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $limit = \SqlSemantics\Platform\MySql\Rules\Typing\Casts::stringLimit($target, $release);
        if (!in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) && in_array($target->kind, [CastKind::Char, CastKind::NationalChar], true) && $limit !== null && $limit > intdiv(4294967295, $domain->collation->charset->maxLength)) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange());
        }
    }


    /**
     * Derives a character-set spelling and its lookup condition.
     */
    public function charset(string $name, Derivation $derivation): void
    {
        Deprecation::charset($name, $derivation);
        if (Charset::named($name) === null) {
            $problem = new UnknownCharset($name);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
    }
}
