<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `ROTATE {INNODB | BINLOG} MASTER KEY`: rotate the master encryption key of InnoDB or of the binary log.
 *
 * Mirrors ROTATE_INNODB_MASTER_KEY and ROTATE_BINLOG_MASTER_KEY (BINLOG from
 * MySQL 8.0.14). The name is written as an identifier or a string; any other
 * name is a syntax error of the server and cannot be constructed.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility public
 * @example Rotating the binary log master key
 *     $rotate = new \SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey(new \SqlSemantics\Statement\Identifier\Name('binlog'));
 *     $rotate->binaryLog() // => true
 */
final class RotateMasterKey implements InstanceAction
{
    use Snapshot;

    /**
     * @param Name $keyring The name INNODB or BINLOG, in any letter case
     * @throws InvalidConstruction When the name is neither
     */
    public function __construct(public readonly Name $keyring)
    {
        Check::input(in_array(strtoupper($keyring->value), ['INNODB', 'BINLOG'], true), 'ALTER INSTANCE ROTATE names INNODB or BINLOG.');
    }

    /**
     * Tells whether the binary log master key is rotated rather than the InnoDB one.
     */
    public function binaryLog(): bool
    {
        return strtoupper($this->keyring->value) === 'BINLOG';
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROTATE')->name($this->keyring, NameUse::Identifier)->keyword('MASTER', 'KEY');
    }
}
