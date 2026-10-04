<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `READ ONLY [=] {DEFAULT | 0 | 1}`: whether the database may be modified (ALTER DATABASE, MySQL 8.0.22 and later).
 *
 * A number other than 0 makes the database read-only; 0 and DEFAULT make
 * it writable. The equals sign is optional and not written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 *
 * @visibility public
 * @example Making a database read-only
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER DATABASE d READ ONLY = 1');
 *     [$alter->toString(), $alter->statement->options[0]->readOnly()] // => ['ALTER DATABASE d READ ONLY 1', true]
 */
final class DatabaseReadOnly implements DatabaseOption
{
    use Snapshot;

    /**
     * @param Numeral|null $value The number; null for DEFAULT
     */
    public function __construct(public readonly ?Numeral $value)
    {
    }

    /**
     * Tells whether the option makes the database read-only: a number other than 0.
     */
    public function readOnly(): bool
    {
        return $this->value !== null && ltrim($this->value->hexadecimal ? $this->value->text : (string) preg_replace('/[.eE].*\z/s', '', $this->value->text), '0') !== '';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('READ', 'ONLY');
        if ($this->value === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->node($this->value);
        }
    }
}
