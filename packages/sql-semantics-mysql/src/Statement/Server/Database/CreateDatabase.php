<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CREATE {DATABASE | SCHEMA} [IF NOT EXISTS] name [option …]`: a request to create a database.
 *
 * Mirrors SQLCOM_CREATE_DB with HA_CREATE_INFO. Rule:
 * MYSQL-CREATE-DATABASE-001. The options are kept in written order. A
 * database holds no columns, so the statement declares no relation.
 * SCHEMA is a synonym of DATABASE, which the lexer folds.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Creating a database with options
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('create schema if not exists shop default charset utf8mb4 collate utf8mb4_bin');
 *     [$create->toString(), count($create->statement->options)] // => ['CREATE DATABASE IF NOT EXISTS shop CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', 2]
 */
final class CreateDatabase implements Statement
{
    use Snapshot;

    /**
     * @var list<DatabaseOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param Name $name The database name
     * @param list<DatabaseOption> $options The options in written order
     * @throws InvalidConstruction When an option is READ ONLY, which only ALTER DATABASE takes
     */
    public function __construct(public readonly bool $ifNotExists, public readonly Name $name, array $options = [])
    {
        $this->options = Check::listOf($options, DatabaseOption::class, 'CREATE DATABASE takes a list of options.');
        foreach ($this->options as $option) {
            Check::input(!$option instanceof DatabaseReadOnly, 'READ ONLY is an option of ALTER DATABASE.');
        }
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
        $out->keyword('CREATE', 'DATABASE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->name($this->name, NameUse::Qualifier);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
