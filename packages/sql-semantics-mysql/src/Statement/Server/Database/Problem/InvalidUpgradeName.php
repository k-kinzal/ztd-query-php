<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An upgrade request does not identify a legacy database directory.
 *
 * The decoded identifier supplies diagnostic display without retaining input SQL.
 *
 * @visibility public
 * @example Retaining the rejected identity
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName(new \SqlSemantics\Statement\Identifier\Name('ordinary')))->message() // => 'Incorrect usage of ALTER DATABASE UPGRADE DATA DIRECTORY NAME and name'
 */
final class InvalidUpgradeName implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The identifier that lacks a legacy marker or valid component
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Distinguishes a missing marker from an invalid directory component.
     */
    public function message(): string
    {
        if ($this->identifierTooLong()) {
            return "Identifier name '" . $this->name->value . "' is too long";
        }
        return str_starts_with($this->name->value, '#mysql50#')
            ? "Incorrect database name '" . $this->name->value . "'"
            : 'Incorrect usage of ALTER DATABASE UPGRADE DATA DIRECTORY NAME and name';
    }
    /**
     * Distinguishes a well-formed component exceeding 64 characters from other invalid names.
     */
    public function identifierTooLong(): bool
    {
        return str_starts_with($this->name->value, '#mysql50#')
            && \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName::wellFormed(substr($this->name->value, 9))
            && mb_strlen(\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName::converted(substr($this->name->value, 9)), 'UTF-8') > 64;
    }

}
