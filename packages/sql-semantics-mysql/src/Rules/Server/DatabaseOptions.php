<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Resolves names in database options before a database is opened or changed.
 *
 * Only diagnostics and their ordered notices are produced; database state is untouched.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-database.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DatabaseOptions
{
    /**
     * Reports unknown character sets and unavailable collations in occurrence order.
     *
     * @param list<DatabaseOption> $options The typed options in written order
     */
    public function derive(array $options, Derivation $derivation): void
    {
        foreach ($options as $option) {
            $problem = match (true) {
                $option instanceof DatabaseCollation && $option->collation->name !== null && !(Collation::named($option->collation->name->value)?->availableIn($derivation->context->profile->grammar) ?? false) => new UnknownCollation($option->collation->name->value),
                $option instanceof DatabaseCharset && $option->charset->name !== null && Charset::named($option->charset->name->value) === null => new UnknownCharset($option->charset->name->value),
                default => null,
            };
            if ($problem !== null) {
                $derivation->report($problem);
                $derivation->warn(new ParseFailure($problem, true), $option);
            }
        }
    }
}
