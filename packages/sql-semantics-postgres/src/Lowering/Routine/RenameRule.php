<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedMember;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;

/**
 * Lowers ALTER ... RENAME.
 *
 * Rule: PG-RENAME-LOWER-001. Scope: `RenameStmt`. Constructor: `Rename`, as
 * PostgreSQL builds one `RenameStmt` for every kind; the object part is read
 * by PG-OBJECT-LOWER-001. COLUMN before a renamed column is a noise word.
 * ALTER GROUP, ROLE and USER rename a role and keep their keyword.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/sql-alterrole.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RenameRule
{
    /**
     * The kind each production renames.
     */
    private const KINDS = [
        'RenameStmt: ALTER AGGREGATE aggregate_with_argtypes RENAME TO name' => 'AGGREGATE',
        'RenameStmt: ALTER COLLATION any_name RENAME TO name' => 'COLLATION',
        'RenameStmt: ALTER CONVERSION_P any_name RENAME TO name' => 'CONVERSION',
        'RenameStmt: ALTER DATABASE name RENAME TO name' => 'DATABASE',
        'RenameStmt: ALTER DOMAIN_P any_name RENAME TO name' => 'DOMAIN',
        'RenameStmt: ALTER DOMAIN_P any_name RENAME CONSTRAINT name TO name' => 'DOMAIN',
        'RenameStmt: ALTER FOREIGN DATA_P WRAPPER name RENAME TO name' => 'FOREIGN DATA WRAPPER',
        'RenameStmt: ALTER FUNCTION function_with_argtypes RENAME TO name' => 'FUNCTION',
        'RenameStmt: ALTER GROUP_P RoleId RENAME TO RoleId' => 'ROLE',
        'RenameStmt: ALTER opt_procedural LANGUAGE name RENAME TO name' => 'LANGUAGE',
        'RenameStmt: ALTER OPERATOR CLASS any_name USING name RENAME TO name' => 'OPERATOR CLASS',
        'RenameStmt: ALTER OPERATOR FAMILY any_name USING name RENAME TO name' => 'OPERATOR FAMILY',
        'RenameStmt: ALTER POLICY name ON qualified_name RENAME TO name' => 'POLICY',
        'RenameStmt: ALTER POLICY IF_P EXISTS name ON qualified_name RENAME TO name' => 'POLICY',
        'RenameStmt: ALTER PROCEDURE function_with_argtypes RENAME TO name' => 'PROCEDURE',
        'RenameStmt: ALTER PUBLICATION name RENAME TO name' => 'PUBLICATION',
        'RenameStmt: ALTER ROUTINE function_with_argtypes RENAME TO name' => 'ROUTINE',
        'RenameStmt: ALTER SCHEMA name RENAME TO name' => 'SCHEMA',
        'RenameStmt: ALTER SERVER name RENAME TO name' => 'SERVER',
        'RenameStmt: ALTER SUBSCRIPTION name RENAME TO name' => 'SUBSCRIPTION',
        'RenameStmt: ALTER TABLE relation_expr RENAME TO name' => 'TABLE',
        'RenameStmt: ALTER TABLE IF_P EXISTS relation_expr RENAME TO name' => 'TABLE',
        'RenameStmt: ALTER SEQUENCE qualified_name RENAME TO name' => 'SEQUENCE',
        'RenameStmt: ALTER SEQUENCE IF_P EXISTS qualified_name RENAME TO name' => 'SEQUENCE',
        'RenameStmt: ALTER VIEW qualified_name RENAME TO name' => 'VIEW',
        'RenameStmt: ALTER VIEW IF_P EXISTS qualified_name RENAME TO name' => 'VIEW',
        'RenameStmt: ALTER MATERIALIZED VIEW qualified_name RENAME TO name' => 'MATERIALIZED VIEW',
        'RenameStmt: ALTER MATERIALIZED VIEW IF_P EXISTS qualified_name RENAME TO name' => 'MATERIALIZED VIEW',
        'RenameStmt: ALTER INDEX qualified_name RENAME TO name' => 'INDEX',
        'RenameStmt: ALTER INDEX IF_P EXISTS qualified_name RENAME TO name' => 'INDEX',
        'RenameStmt: ALTER FOREIGN TABLE relation_expr RENAME TO name' => 'FOREIGN TABLE',
        'RenameStmt: ALTER FOREIGN TABLE IF_P EXISTS relation_expr RENAME TO name' => 'FOREIGN TABLE',
        'RenameStmt: ALTER TABLE relation_expr RENAME opt_column name TO name' => 'TABLE',
        'RenameStmt: ALTER TABLE IF_P EXISTS relation_expr RENAME opt_column name TO name' => 'TABLE',
        'RenameStmt: ALTER VIEW qualified_name RENAME opt_column name TO name' => 'VIEW',
        'RenameStmt: ALTER VIEW IF_P EXISTS qualified_name RENAME opt_column name TO name' => 'VIEW',
        'RenameStmt: ALTER MATERIALIZED VIEW qualified_name RENAME opt_column name TO name' => 'MATERIALIZED VIEW',
        'RenameStmt: ALTER MATERIALIZED VIEW IF_P EXISTS qualified_name RENAME opt_column name TO name' => 'MATERIALIZED VIEW',
        'RenameStmt: ALTER TABLE relation_expr RENAME CONSTRAINT name TO name' => 'TABLE',
        'RenameStmt: ALTER TABLE IF_P EXISTS relation_expr RENAME CONSTRAINT name TO name' => 'TABLE',
        'RenameStmt: ALTER FOREIGN TABLE relation_expr RENAME opt_column name TO name' => 'FOREIGN TABLE',
        'RenameStmt: ALTER FOREIGN TABLE IF_P EXISTS relation_expr RENAME opt_column name TO name' => 'FOREIGN TABLE',
        'RenameStmt: ALTER RULE name ON qualified_name RENAME TO name' => 'RULE',
        'RenameStmt: ALTER TRIGGER name ON qualified_name RENAME TO name' => 'TRIGGER',
        'RenameStmt: ALTER EVENT TRIGGER name RENAME TO name' => 'EVENT TRIGGER',
        'RenameStmt: ALTER ROLE RoleId RENAME TO RoleId' => 'ROLE',
        'RenameStmt: ALTER USER RoleId RENAME TO RoleId' => 'ROLE',
        'RenameStmt: ALTER TABLESPACE name RENAME TO name' => 'TABLESPACE',
        'RenameStmt: ALTER STATISTICS any_name RENAME TO name' => 'STATISTICS',
        'RenameStmt: ALTER TEXT_P SEARCH PARSER any_name RENAME TO name' => 'TEXT SEARCH PARSER',
        'RenameStmt: ALTER TEXT_P SEARCH DICTIONARY any_name RENAME TO name' => 'TEXT SEARCH DICTIONARY',
        'RenameStmt: ALTER TEXT_P SEARCH TEMPLATE any_name RENAME TO name' => 'TEXT SEARCH TEMPLATE',
        'RenameStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name RENAME TO name' => 'TEXT SEARCH CONFIGURATION',
        'RenameStmt: ALTER TYPE_P any_name RENAME TO name' => 'TYPE',
        'RenameStmt: ALTER TYPE_P any_name RENAME ATTRIBUTE name TO name opt_drop_behavior' => 'TYPE',
    ];

    /**
     * The role keyword of each role production.
     */
    private const ROLE_WORDS = ['GROUP_P' => RoleWord::Group, 'ROLE' => RoleWord::Role, 'USER' => RoleWord::User];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `RenameStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function rename(Node $statement): Rename
    {
        $form = $this->lowering->productions->form($statement);
        $kind = ObjectKind::from(self::KINDS[$form->signature] ?? throw ImplementationGap::production($form));
        $objects = new ObjectRule($this->lowering);
        [$object, $next] = $objects->altered($form, $objects->start($form));
        $member = $this->member($form, $next + 1);
        $at = $next + ($member === null ? 2 : 4);
        $target = $form->node($at);
        $newName = $target->name === 'RoleId' ? $this->lowering->roles->name($target) : $this->lowering->names->name($target);
        $behavior = count($form->node->children) > $at + 1 ? $this->lowering->flags->dropBehavior($form->node($at + 1)) : null;
        $roleWord = $kind === ObjectKind::Role ? self::ROLE_WORDS[$form->token(1)->name] ?? throw ImplementationGap::production($form) : null;

        return new Rename($kind, $object, $newName, $member, $objects->has($form, 'IF_P'), $behavior, $roleWord);
    }

    /**
     * Lowers the renamed part that may follow RENAME at a position: `opt_column name`, `CONSTRAINT name` or `ATTRIBUTE name`; TO is null.
     */
    public function member(Form $form, int $at): ?RenamedMember
    {
        $objects = new ObjectRule($this->lowering);
        $child = $form->node->children[$at] ?? null;
        if ($child instanceof Node) {
            $this->lowering->flags->present($child);

            return new RenamedMember(RenamedPart::Column, $this->lowering->names->name($form->node($at + 1)));
        }
        foreach ([RenamedPart::Constraint, RenamedPart::Attribute] as $part) {
            if ($objects->token($form, $at, $part->value)) {
                return new RenamedMember($part, $this->lowering->names->name($form->node($at + 1)));
            }
        }

        return null;
    }
}
