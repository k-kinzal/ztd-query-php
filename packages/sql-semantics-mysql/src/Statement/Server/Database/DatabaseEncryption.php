<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * `[DEFAULT] ENCRYPTION [=] {'Y' | 'N'}`: the default encryption of the database's tables (MySQL 8.0.16 and later).
 *
 * DEFAULT before the option and the optional equals sign are retained.
 * Known values resolve to a two-case setting; an unknown quoted operand remains
 * available on the complete request. A noncanonical known operand may be retained for rendering;
 * construction checks that it denotes exactly the same setting. It is a
 * literal value, without input SQL or parser nodes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility public
 * @example Reading the encryption
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE DATABASE d ENCRYPTION 'Y'");
 *     $create->options[0]->encryption->value // => 'Y'
 */
final class DatabaseEncryption implements DatabaseOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * A noncanonical decoded operand, or null for the canonical Y/N value.
     */
    public readonly ?Text $spelling;

    /**
     * @param Encryption $encryption The resolved setting
     * @param Text|null $spelling A quoted operand denoting the same setting
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     * @param bool $defaultKeyword Whether the optional DEFAULT keyword is written
     */
    public function __construct(public readonly Encryption $encryption, ?Text $spelling = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null, public readonly bool $defaultKeyword = true)
    {
        Check::input($spelling === null || ($spelling->radix === null && Encryption::fromOperand($spelling->value) === $encryption), 'An encryption spelling denotes the same Y/N setting.');
        $this->spelling = $spelling?->value === $encryption->value ? null : $spelling;
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->defaultKeyword) {
            $out->keyword('DEFAULT');
        }
        $out->keyword('ENCRYPTION');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        $out->spelled($out->quote($this->spelling->value ?? $this->encryption->value));
    }
}
