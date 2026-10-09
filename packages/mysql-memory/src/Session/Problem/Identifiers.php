<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Refuses a name longer than 64 characters where the server reads the name of a database, a table, a view, a stored program or function, a column it defines, an index or a constraint (ER_TOO_LONG_IDENT).
 *
 * The parser checks the database and the name of every table or program a statement names, the
 * database of the statements that name one, and the columns and indexes a definition names, in
 * written order and the database before the name. An alias and the table qualifying a column
 * are not checked; the database qualifying a column, and that of a privilege level, is, except
 * in MySQL 5.6 and 5.7, which ignore the name of a CHECK constraint; MySQL 5.6 does not check DROP FUNCTION IF EXISTS (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and
 * 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html.
 *
 * @visibility MySqlMemory
 */
final class Identifiers
{
    /**
     * The longest name the server takes.
     */
    public const LONGEST = 64;

    /**
     * Raises the error of the first name of a statement that is too long.
     *
     * @throws \MySqlMemory\Error\SqlError When a name is too long
     */
    public function check(Node $statement, GrammarRelease $release): void
    {
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            foreach ($this->names($node, $release) as $name) {
                if ($name !== null && mb_strlen($name->value) > self::LONGEST) {
                    throw SchemaError::TooLongIdentifier->error($name->value);
                }
            }
        }
    }

    /**
     * Answers the names of a node the server checks, in written order.
     *
     * @return list<Name|null>
     */
    public function names(Node $node, GrammarRelease $release): array
    {
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;

        return match (true) {
            $node instanceof ColumnUse => $legacy ? [] : [$node->qualifier?->schema],
            $node instanceof ColumnName => $legacy ? [] : [$node->table?->schema],
            $node instanceof TableWildcard => [],
            $node instanceof DropProgram && $release === GrammarRelease::MySql5651 && $node->kind === ProgramKind::Function && $node->ifExists => [],
            $node instanceof CreateDatabase, $node instanceof DropDatabase, $node instanceof AlterDatabase, $node instanceof ShowCreateDatabase => [$node->name],
            $node instanceof DatabaseLevel => $legacy ? [] : [$node->database],
            $node instanceof FunctionCall => [$node->schema, $node->name],
            default => $this->definitions($node, $legacy) ?? $this->properties($node),
        };
    }

    /**
     * Answers the names a definition of a column, an index or a constraint gives, in written order, or null for a node that defines none of them.
     *
     * @param bool $legacy Whether the release is MySQL 5.6 or 5.7, which ignore CHECK constraints
     * @return list<Name|null>|null
     */
    public function definitions(Node $node, bool $legacy): ?array
    {
        return match (true) {
            $node instanceof ColumnDefinition => [$node->name->column],
            $node instanceof IndexDefinition, $node instanceof ForeignKey => [$node->constraint?->name?->column, $node->name?->column],
            $node instanceof CheckConstraint => $legacy ? [] : [$node->name?->name?->column],
            $node instanceof ConstraintName => [],
            $node instanceof CreateIndex => [$node->name, $node->table->schema, $node->table->name],
            default => null,
        };
    }

    /**
     * Answers the database and table names a node holds: its qualified names, the database before the name, and a property named database.
     *
     * @return list<Name|null>
     */
    public function properties(Node $node): array
    {
        $names = [];
        foreach (get_object_vars($node) as $property => $value) {
            foreach (is_array($value) ? $value : [$value] as $member) {
                if ($member instanceof QualifiedName) {
                    array_push($names, $member->schema, $member->name);
                } elseif ($member instanceof Name && $property === 'database') {
                    $names[] = $member;
                }
            }
        }

        return $names;
    }
}
