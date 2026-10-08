<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One `option = value` of CHANGE REPLICATION SOURCE TO, or one log position of the UNTIL condition of START REPLICA.
 *
 * Mirrors one field of the server's LEX_SOURCE_INFO. The value is typed by
 * the option (SourceOptionKind::accepts); NULL is the value null, accepted by
 * PRIVILEGE_CHECKS_USER and SOURCE_TLS_CIPHERSUITES. The option is written in
 * the vocabulary of its statement; in 8.0 to 8.3 an option of the current
 * vocabulary may be spelled with MASTER, its synonym ($synonym).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Holding a typed option value
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Port, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3306'));
 *     $option->kind->value // => 'SOURCE_PORT'
 */
final class SourceOption implements Node
{
    use Snapshot;

    /**
     * @param Terminology $terminology The vocabulary of the statement the option belongs to
     * @param SourceOptionKind $kind The option
     * @param Text|Numeral|NumberLiteral|ServerIds|AccountName|PrimaryKeyCheck|AnonymousGtids|null $value The value, of the domain of the option
     * @param bool $synonym Whether the option is written with MASTER where the release reads it as its SOURCE synonym, which MySQL 8.0 to 8.3 warn deprecated
     */
    public function __construct(
        public readonly Terminology $terminology,
        public readonly SourceOptionKind $kind,
        public readonly Text|Numeral|NumberLiteral|ServerIds|AccountName|PrimaryKeyCheck|AnonymousGtids|null $value,
        public readonly bool $synonym = false,
    ) {
        Check::input($kind->accepts($value), 'The value of ' . $kind->value . ' is outside the domain of the option.');
    }

    /**
     * Writes the option, `=` and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->keyword($this->synonym ? Terminology::Legacy : $this->terminology))->symbol('=');
        if ($this->value instanceof PrimaryKeyCheck || $this->value instanceof AnonymousGtids) {
            $out->keyword($this->value->value);
        } elseif ($this->value === null) {
            $out->keyword('NULL');
        } else {
            $out->node($this->value);
        }
    }
}
