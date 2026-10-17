<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `[DEFAULT] ENCRYPTION [=] {'Y' | 'N'}`: the default encryption of the database's tables (MySQL 8.0.16 and later).
 *
 * DEFAULT before the option is optional and always written, because
 * ENCRYPTION right after ALTER DATABASE would be read as the database name;
 * the equals sign is optional and not written. The value is a two-case
 * setting. A noncanonical decoded operand may be retained for rendering;
 * construction checks that it denotes exactly the same setting. It is a
 * literal value, without input SQL or parser nodes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility public
 * @example Reading the encryption
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE DATABASE d ENCRYPTION 'Y'");
 *     $create->statement->options[0]->encryption->value // => 'Y'
 */
final class DatabaseEncryption implements DatabaseOption
{
    use Snapshot;

    /**
     * A noncanonical decoded operand, or null for the canonical Y/N value.
     */
    public readonly ?Text $spelling;

    /**
     * @param Encryption $encryption Whether new tables use encryption by default
     * @param Text|null $spelling A quoted operand denoting the same setting
     */
    public function __construct(public readonly Encryption $encryption, ?Text $spelling = null)
    {
        Check::input($spelling === null || ($spelling->radix === null && Encryption::fromOperand($spelling->value) === $encryption), 'An encryption spelling denotes the same Y/N setting.');
        $this->spelling = $spelling?->value === $encryption->value ? null : $spelling;
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT', 'ENCRYPTION')->spelled($out->quote($this->spelling->value ?? $this->encryption->value));
    }
}
