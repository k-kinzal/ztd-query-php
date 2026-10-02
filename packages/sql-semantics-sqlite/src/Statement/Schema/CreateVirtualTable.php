<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a virtual table: a table whose rows and columns a module provides.
 *
 * Rule: SQLITE-CREATE-VIRTUAL-TABLE-001. The module is named and receives the
 * arguments as text (SQLITE-MODULE-ARGUMENT-001). The columns of the table,
 * its hidden columns and whether it has a rowid are decided by the module
 * when it is created, not by the statement: the statement provides a
 * declaration of the table name without columns that is marked incomplete,
 * so a use of the table depends on its missing member list. A module name
 * that is not registered on the connection is not a fact of the model. A
 * definition with empty parentheses and one without parentheses are the same
 * request to SQLite; the model keeps which was written.
 * Source: https://sqlite.org/lang_createvtab.html, https://sqlite.org/vtab.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Declaring a virtual table without knowing its columns
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE VIRTUAL TABLE docs USING fts5(title, body)');
 *     [$create->statement->module->value, count($create->statement->arguments), $create->declarations()[0]->complete] // => ['fts5', 2, false]
 */
final class CreateVirtualTable implements Statement
{
    use Snapshot;

    /**
     * @var list<ModuleArgument>|null The module arguments in order, or null when no parentheses are written
     */
    public readonly ?array $arguments;

    /**
     * @param QualifiedName $name The table name
     * @param Name $module The module that implements the table
     * @param list<ModuleArgument>|null $arguments The module arguments in order; null when no parentheses are written, otherwise at least one, possibly empty, argument
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(public readonly QualifiedName $name, public readonly Name $module, ?array $arguments = null, public readonly bool $ifNotExists = false)
    {
        Check::input($name->catalog === null, 'A table name has at most a schema qualifier.');
        $this->arguments = $arguments === null ? null : Check::listOf($arguments, ModuleArgument::class, 'Written parentheses hold at least one, possibly empty, module argument.', 1);
    }

    /**
     * Provides the declaration of the table name with an unknown column list.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->declare(new Table($this->name, $derivation->context->profile, [], [], false));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'VIRTUAL', 'TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
        $out->keyword('USING')->name($this->module, NameUse::Routine);
        if ($this->arguments !== null) {
            $out->symbol('(')->list($this->arguments)->symbol(')');
        }
    }
}
