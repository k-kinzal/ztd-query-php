<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;

/**
 * Resolves explicitly named SET encodings before preparing assignment values.
 *
 * Unknown names stop grammar interpretation, including inside an unexecuted
 * stored instruction. A known charset/collation mismatch is contextualized
 * later on modern releases. DEFAULT and client-variable assignments retain
 * their session-dependent checks. Observed on MySQL 5.6.51, 5.7.44 and 8.4.7.
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
     * Resolves an explicit collation before checking its charset compatibility.
     */
    public function collation(SetNames $item, Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $name = $item->collation?->name;
        if ($name === null) {
            return;
        }
        $collation = Collation::forName($name->value, $release);
        if ($collation === null) {
            $problem = new UnknownCollation($name->value);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true), $item->collation);
            return;
        }
        $charset = $item->charset->name === null ? null : Charset::named($item->charset->name->value);
        if ($charset !== null && $collation->charset !== $charset) {
            $problem = new CollationMismatch($collation->nameIn($release), $charset->nameIn($release));
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true, NoticePhase::Contextualization), $item);
        }
    }
}
