<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `CONVERT TO CHARACTER SET name | DEFAULT [COLLATE name]`: a request to convert every character column and the table default.
 *
 * Mirrors PT_alter_table_convert_to_charset. DEFAULT means the character
 * set of the database. CHARACTER SET and CHARSET are synonyms; CHARACTER
 * SET is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-character-set.
 *
 * @visibility public
 * @example Converting a table to utf8mb4
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARSET utf8mb4');
 *     [$alter->statement->commands[0]->charset->name?->value, $alter->toString()] // => ['utf8mb4', 'ALTER TABLE t CONVERT TO CHARACTER SET utf8mb4']
 */
final class ConvertCharset implements AlterCommand
{
    use Snapshot;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     * @param CollationName|null $collation The COLLATE clause, when written
     */
    public function __construct(public readonly CharsetName $charset, public readonly ?CollationName $collation = null)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONVERT', 'TO', 'CHARACTER', 'SET')->node($this->charset);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
    }
}
