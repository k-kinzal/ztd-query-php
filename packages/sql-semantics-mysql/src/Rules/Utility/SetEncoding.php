<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;

/**
 * Resolves explicitly named SET encodings before preparing assignment values.
 *
 * Unknown names stop grammar interpretation, including inside an unexecuted
 * stored instruction. A collation of another character set than the named one
 * is refused while reading, in every release and inside stored program bodies
 * (ER_COLLATION_CHARSET_MISMATCH); SetNames refuses such a pair as an
 * invariant. DEFAULT and client-variable assignments retain their
 * session-dependent checks. Observed on MySQL 5.6.51, 5.7.44 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-names.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SetEncoding
{
    /**
     * Derives explicit encoding names in their written order.
     */
    public function derive(SetNames|SetCharacterSet $item, Derivation $derivation): void
    {
        $name = $item->charset->name;
        if ($name !== null && Charset::named($name->value) === null) {
            $problem = new UnknownCharset($name->value);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true), $item->charset);
            return;
        }
        if (!$item instanceof SetNames) {
            return;
        }
        if ($name !== null) {
            Deprecation::charset($name->value, $derivation, $item->charset);
        }
        $notice = $item->collation?->deprecation($derivation->context->profile->grammar);
        if ($notice !== null) {
            $derivation->warn($notice, $item->collation);
        }
        $this->collation($item, $derivation);
    }

    /**
     * Resolves an explicit collation the selected release knows.
     */
    public function collation(SetNames $item, Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $name = $item->collation?->name;
        if ($name === null) {
            return;
        }
        if (Collation::forName($name->value, $release) === null) {
            $problem = new UnknownCollation($name->value);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true), $item->collation);
        }
    }

    /**
     * Answers the mismatch of an explicit collation that belongs to another character set than the named one, or null when either name is unknown to the catalog, DEFAULT, or they agree.
     */
    public function mismatch(CharsetName $charset, ?CollationName $collation, GrammarRelease $release = GrammarRelease::MySql847): ?CollationMismatch
    {
        $named = $charset->name === null ? null : Charset::named($charset->name->value);
        $compared = $collation?->name === null ? null : Collation::named($collation->name->value);
        if ($named === null || $compared === null || $compared->charset->name === $named->name) {
            return null;
        }

        return new CollationMismatch($compared->nameIn($release), $named->nameIn($release));
    }
}
