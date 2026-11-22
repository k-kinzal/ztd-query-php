<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ALTER {DATABASE | SCHEMA} [name] option …`: a request to change the options of a database.
 *
 * Mirrors SQLCOM_ALTER_DB. Rule: MYSQL-ALTER-DATABASE-001. Without a name
 * the current database is altered. The options are kept in written order.
 * The statement changes no declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDatabase implements Statement
{
    use Snapshot;

    /**
     * @var list<DatabaseOption> The options in written order; at least one
     */
    public readonly array $options;

    /**
     * @param Name|null $name The database name; null for the current database
     * @param list<DatabaseOption> $options The options in written order; at least one
     * @throws InvalidConstruction When there is no option
     */
    public function __construct(public readonly ?Name $name, array $options)
    {
        $this->options = Check::listOf($options, DatabaseOption::class, 'ALTER DATABASE changes at least one option.', 1);
    }

    /**
     * Has nothing to derive: a database is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DATABASE');
        if ($this->name !== null) {
            $out->name($this->name, NameUse::Qualifier);
        }
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
