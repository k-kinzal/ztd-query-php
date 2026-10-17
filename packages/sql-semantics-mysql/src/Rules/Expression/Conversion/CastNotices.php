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
            Deprecation::charset($target->charset->charset->value, $derivation);
            if (Charset::named($target->charset->charset->value) === null) {
                $problem = new UnknownCharset($target->charset->charset->value);
                $derivation->report($problem);
                $derivation->warn(new ParseFailure($problem, true));
            }
        }
    }
}
