<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\ReadOnlyConflict;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Fact\Diagnostic;

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
     * Reports option conflicts, invalid values and charset notices in occurrence order.
     *
     * @param list<DatabaseOption> $options The typed options in written order
     */
    public function derive(array $options, Derivation $derivation): void
    {
        $problems = $this->problems($options, $derivation->context->profile->grammar);
        foreach ($options as $index => $option) {
            if ($option instanceof DatabaseCharset && $option->charset->name !== null) {
                \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::charset($option->charset->name->value, $derivation, $option);
            }
            $notice = $this->collationNotice($option, $derivation->context->profile->grammar);
            if ($notice !== null) {
                $derivation->warn($notice, $option);
            }
            if (isset($problems[$index])) {
                $derivation->report($problems[$index]);
                $derivation->warn(new ParseFailure($problems[$index], true), $option);
            }
        }
    }

    /**
     * Finds option-local failures and conflicts, retaining their occurrence positions.
     *
     * @param list<DatabaseOption> $options The ordered database options
     * @return array<int, Diagnostic> Problems indexed by the conflicting or invalid option
     */
    public function problems(array $options, GrammarRelease $release): array
    {
        $problems = [];
        $charset = null;
        $readOnly = null;
        foreach ($options as $index => $option) {
            $problem = $this->invalid($option, $release);
            if ($option instanceof DatabaseReadOnly) {
                $value = $option->readOnly();
                $problem ??= $readOnly !== null && $readOnly !== $value ? new ReadOnlyConflict() : null;
                $readOnly = $value;
            }
            $named = $this->charset($option);
            $problem ??= $this->charsetConflict($option, $charset, $named, $release);
            $charset = $named ?? $charset;
            if ($problem !== null) {
                $problems[$index] = $problem;
            }
        }

        return $problems;
    }

    /**
     * Answers the known character set selected by an explicit charset or collation.
     */
    public function charset(DatabaseOption $option): ?Charset
    {
        return match (true) {
            $option instanceof DatabaseCharset && $option->charset->name !== null => Charset::named($option->charset->name->value),
            $option instanceof DatabaseCollation && $option->collation->name !== null => Collation::named($option->collation->name->value)?->charset,
            default => null,
        };
    }

    /**
     * Compares an explicit option with the preceding resolved charset.
     */
    public function charsetConflict(DatabaseOption $option, ?Charset $earlier, ?Charset $named, GrammarRelease $release = GrammarRelease::MySql847): ?Diagnostic
    {
        if ($earlier === null || $named === null || $earlier->name === $named->name) {
            return null;
        }
        return match (true) {
            $option instanceof DatabaseCharset => new CharsetConflict($earlier, $named),
            $option instanceof DatabaseCollation && $option->collation->name !== null => new CollationMismatch(Collation::named($option->collation->name->value)?->nameIn($release) ?? $option->collation->name->value, $earlier->nameIn($release)),
            default => null,
        };
    }

    /**
     * Describes a deprecated database collation using its resolved name.
     */
    public function collationNotice(DatabaseOption $option, GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation
    {
        return $option instanceof DatabaseCollation ? $option->collation->deprecation($release) : null;
    }

    /**
     * Resolves an individual option without relying on the selected database.
     */
    public function invalid(DatabaseOption $option, GrammarRelease $release): ?Diagnostic
    {
        return match (true) {
            $option instanceof DatabaseCollation && $option->collation->name !== null && Collation::forName($option->collation->name->value, $release) === null => new UnknownCollation($option->collation->name->value),
            $option instanceof DatabaseCharset && $option->charset->name !== null && Charset::named($option->charset->name->value) === null => new UnknownCharset($option->charset->name->value),
            $option instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption && $option->encryption instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Text => new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption($option->encryption),
            default => null,
        };
    }
}
