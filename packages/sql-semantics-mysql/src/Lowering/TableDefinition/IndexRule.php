<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyOptionRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyPartRule;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Lowers CREATE INDEX.
 *
 * Rule: MYSQL-CREATE-INDEX-LOWERING-001. Scope: the `create` alternatives
 * `CREATE opt_unique INDEX_SYM …`, `CREATE fulltext INDEX_SYM …` and
 * `CREATE spatial INDEX_SYM …` (5.x), create_index_stmt (8.0 and later),
 * opt_unique, fulltext, spatial. The ALGORITHM and LOCK clauses come from
 * the table change family. Constructs: CreateIndex. Terminates: every child
 * is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IndexRule
{
    /**
     * The productions: positions of the kind, the name, the structure clause, the table, the key parts, the options and the ALGORITHM and LOCK clauses.
     */
    private const FORMS = [
        'create: CREATE opt_unique INDEX_SYM ident key_alg ON table_ident ( key_list ) normal_key_options opt_index_lock_algorithm' => [1, 3, 4, 6, 8, 10, 11],
        'create: CREATE fulltext INDEX_SYM ident init_key_options ON table_ident ( key_list ) fulltext_key_options opt_index_lock_algorithm' => [1, 3, 4, 6, 8, 10, 11],
        'create: CREATE spatial INDEX_SYM ident init_key_options ON table_ident ( key_list ) spatial_key_options opt_index_lock_algorithm' => [1, 3, 4, 6, 8, 10, 11],
        'create_index_stmt: CREATE opt_unique INDEX_SYM ident opt_index_type_clause ON_SYM table_ident ( key_list_with_expression ) opt_index_options opt_index_lock_and_algorithm' => [1, 3, 4, 6, 8, 10, 11],
        'create_index_stmt: CREATE FULLTEXT_SYM INDEX_SYM ident ON_SYM table_ident ( key_list_with_expression ) opt_fulltext_index_options opt_index_lock_and_algorithm' => [1, 3, null, 5, 7, 9, 10],
        'create_index_stmt: CREATE SPATIAL_SYM INDEX_SYM ident ON_SYM table_ident ( key_list_with_expression ) opt_spatial_index_options opt_index_lock_and_algorithm' => [1, 3, null, 5, 7, 9, 10],
    ];

    /**
     * The kind keyword productions and tokens.
     */
    private const KINDS = [
        'opt_unique:' => IndexKind::Index, 'opt_unique: UNIQUE_SYM' => IndexKind::Unique, 'fulltext: FULLTEXT_SYM' => IndexKind::FullText,
        'spatial: SPATIAL_SYM' => IndexKind::Spatial, 'FULLTEXT_SYM' => IndexKind::FullText, 'SPATIAL_SYM' => IndexKind::Spatial,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE INDEX: the form of a 5.x `create` alternative or of `create_index_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function index(Form $form): CreateIndex
    {
        [$kind, $name, $structure, $table, $parts, $options, $alter] = self::FORMS[$form->signature] ?? throw ImplementationGap::production($form);
        $keyword = $form->node->children[$kind];
        $kindKey = $keyword instanceof Node ? $this->lowering->productions->form($keyword)->signature : $keyword->name;
        $rule = new KeyOptionRule($this->lowering);

        return new CreateIndex(
            $this->lowering->names->identifier($form->node($name)),
            $this->lowering->names->qualified($form->node($table)),
            (new KeyPartRule($this->lowering))->parts($form->node($parts)),
            self::KINDS[$kindKey] ?? throw ImplementationGap::production($form),
            $structure === null ? null : $this->structure($form->node($structure), $rule),
            $rule->options($form->node($options)),
            $this->lowering->tableChanges->alterOptions($form->node($alter)),
        );
    }

    /**
     * Lowers the structure clause before ON: a node of `key_alg`, `opt_index_type_clause` or the marker `init_key_options`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function structure(Node $clause, KeyOptionRule $rule): ?IndexAlgorithm
    {
        if ($clause->name === 'init_key_options') {
            $this->lowering->options->skip($clause);

            return null;
        }

        return $rule->algorithm($clause);
    }

    /**
     * Lowers CREATE INDEX of MySQL 8.0 and later: a node of `create_index_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): CreateIndex
    {
        return $this->index($this->lowering->productions->form($statement));
    }
}
