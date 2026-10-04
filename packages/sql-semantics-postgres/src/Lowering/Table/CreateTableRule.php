<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateForeignTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TableForm;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TypedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\OnCommit;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;

/**
 * Lowers CREATE TABLE and CREATE FOREIGN TABLE.
 *
 * Rule: PG-CREATE-TABLE-LOWER-001. Scope: `CreateStmt`,
 * `CreateForeignTableStmt`, `OptTemp`, `OptTableElementList`,
 * `OptTypedTableElementList`, `TableElementList`, `TypedTableElementList`,
 * `TableElement`, `TypedTableElement`, `OptInherit`,
 * `table_access_method_clause`, `OptWith`, `OnCommitOption`. Constructors:
 * `CreateTable`, `CreateForeignTable`, `ListedColumns`, `TypedTable`,
 * `PartitionOf`, `ParentTable`, `Persistence`, `OnCommit`. WITHOUT OIDS is a
 * noise clause (TableNoise). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateTableRule
{
    /**
     * The persistence each `OptTemp` production writes.
     */
    private const PERSISTENCE = [
        'OptTemp: TEMPORARY' => Persistence::Temporary, 'OptTemp: TEMP' => Persistence::Temp,
        'OptTemp: LOCAL TEMPORARY' => Persistence::LocalTemporary, 'OptTemp: LOCAL TEMP' => Persistence::LocalTemp,
        'OptTemp: GLOBAL TEMPORARY' => Persistence::GlobalTemporary, 'OptTemp: GLOBAL TEMP' => Persistence::GlobalTemp,
        'OptTemp: UNLOGGED' => Persistence::Unlogged, 'OptTemp:' => Persistence::Permanent,
    ];

    /**
     * The form of each `CreateStmt` production: the position of the table name and of the form.
     */
    private const CREATE = [
        'CreateStmt: CREATE OptTemp TABLE qualified_name ( OptTableElementList ) OptInherit OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [3, 'list', 8],
        'CreateStmt: CREATE OptTemp TABLE IF_P NOT EXISTS qualified_name ( OptTableElementList ) OptInherit OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [6, 'list', 11],
        'CreateStmt: CREATE OptTemp TABLE qualified_name OF any_name OptTypedTableElementList OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [3, 'typed', 7],
        'CreateStmt: CREATE OptTemp TABLE IF_P NOT EXISTS qualified_name OF any_name OptTypedTableElementList OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [6, 'typed', 10],
        'CreateStmt: CREATE OptTemp TABLE qualified_name PARTITION OF qualified_name OptTypedTableElementList PartitionBoundSpec OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [3, 'partition', 9],
        'CreateStmt: CREATE OptTemp TABLE IF_P NOT EXISTS qualified_name PARTITION OF qualified_name OptTypedTableElementList PartitionBoundSpec OptPartitionSpec table_access_method_clause OptWith OnCommitOption OptTableSpace' => [6, 'partition', 12],
    ];

    /**
     * The form of each `CreateForeignTableStmt` production: the position of the table name, of the form and of SERVER.
     */
    private const FOREIGN = [
        'CreateForeignTableStmt: CREATE FOREIGN TABLE qualified_name ( OptTableElementList ) OptInherit SERVER name create_generic_options' => [3, 'list', 8],
        'CreateForeignTableStmt: CREATE FOREIGN TABLE IF_P NOT EXISTS qualified_name ( OptTableElementList ) OptInherit SERVER name create_generic_options' => [6, 'list', 11],
        'CreateForeignTableStmt: CREATE FOREIGN TABLE qualified_name PARTITION OF qualified_name OptTypedTableElementList PartitionBoundSpec SERVER name create_generic_options' => [3, 'partition', 9],
        'CreateForeignTableStmt: CREATE FOREIGN TABLE IF_P NOT EXISTS qualified_name PARTITION OF qualified_name OptTypedTableElementList PartitionBoundSpec SERVER name create_generic_options' => [6, 'partition', 12],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): CreateTable
    {
        $form = $this->lowering->productions->form($statement);
        [$at, $kind, $tail] = self::CREATE[$form->signature] ?? throw ImplementationGap::production($form);
        $index = new IndexRule($this->lowering);

        return new CreateTable(
            $this->lowering->names->qualified($form->node($at)),
            $this->form($form, $at, $kind),
            $this->persistence($form->node(1)),
            $at === 6,
            (new PartitionRule($this->lowering))->specification($form->node($tail)),
            $this->method($form->node($tail + 1)),
            $this->storage($form->node($tail + 2)),
            $this->onCommit($form->node($tail + 3)),
            $index->tablespace($form->node($tail + 4)),
        );
    }

    /**
     * Lowers `CreateForeignTableStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function foreign(Node $statement): CreateForeignTable
    {
        $form = $this->lowering->productions->form($statement);
        [$at, $kind, $server] = self::FOREIGN[$form->signature] ?? throw ImplementationGap::production($form);
        $definition = $this->form($form, $at, $kind);
        if (!$definition instanceof ListedColumns && !$definition instanceof PartitionOf) {
            throw ImplementationGap::production($form);
        }

        return new CreateForeignTable(
            $this->lowering->names->qualified($form->node($at)),
            $definition,
            $this->lowering->names->name($form->node($server + 1)),
            $this->lowering->options->genericOptions($form->node($server + 2)),
            $at === 6,
        );
    }

    /**
     * Lowers the form of a table definition that starts after the table name at position `$at`.
     */
    public function form(Form $form, int $at, string $kind): TableForm
    {
        $columns = new ColumnRule($this->lowering);

        return match ($kind) {
            'list' => new ListedColumns($columns->elements($form->node($at + 2)), $this->parents($form->node($at + 4))),
            'typed' => new TypedTable($this->lowering->names->dotted($form->node($at + 2)), $columns->typedElements($form->node($at + 3))),
            default => new PartitionOf(
                new ParentTable($this->lowering->names->qualified($form->node($at + 3))),
                (new PartitionRule($this->lowering))->bound($form->node($at + 5)),
                $columns->typedElements($form->node($at + 4)),
            ),
        };
    }

    /**
     * Lowers `OptTemp`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function persistence(Node $persistence): Persistence
    {
        $form = $this->lowering->productions->form($persistence);

        return self::PERSISTENCE[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `OptInherit`.
     *
     * @return list<ParentTable>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parents(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptInherit: INHERITS ( qualified_name_list )' => array_map(static fn ($name): ParentTable => new ParentTable($name), $this->lowering->names->qualifiedList($form->node(2))),
            'OptInherit:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `table_access_method_clause`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function method(Node $clause): ?\SqlSemantics\Statement\Identifier\Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'table_access_method_clause: USING name' => $this->lowering->names->name($form->node(1)),
            'table_access_method_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptWith`: the storage parameters; WITHOUT OIDS is a no-op.
     *
     * @return list<Definition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function storage(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptWith: WITH reloptions' => $this->lowering->options->definitions($form->node(1)),
            'OptWith: WITHOUT OIDS', 'OptWith:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OnCommitOption`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function onCommit(Node $clause): ?OnCommit
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OnCommitOption: ON COMMIT DROP' => OnCommit::Drop,
            'OnCommitOption: ON COMMIT DELETE_P ROWS' => OnCommit::DeleteRows,
            'OnCommitOption: ON COMMIT PRESERVE ROWS' => OnCommit::PreserveRows,
            'OnCommitOption:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
