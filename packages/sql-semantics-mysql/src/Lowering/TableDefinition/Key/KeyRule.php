<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the index, key and constraint elements of a table definition.
 *
 * Rule: MYSQL-KEY-DEFINITION-001. Scope: key_def (5.x),
 * table_constraint_def (8.0 and later), normal_key_type,
 * constraint_key_type, fulltext, spatial, opt_constraint, constraint,
 * opt_constraint_name, opt_check_constraint, check_constraint, opt_not,
 * opt_constraint_enforcement, constraint_enforcement. KEY and INDEX are
 * synonyms; whether the optional INDEX or KEY follows UNIQUE, FULLTEXT or
 * SPATIAL is kept. Constructs: IndexDefinition, ForeignKey, CheckConstraint,
 * ConstraintName. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class KeyRule
{
    /**
     * The index productions without a CONSTRAINT clause: kind, positions of the optional INDEX word, the name, the structure clause, the key parts and the options.
     */
    private const INDEXES = [
        'key_def: normal_key_type opt_ident key_alg ( key_list ) normal_key_options' => [IndexKind::Index, null, 1, 2, 4, 6],
        'key_def: fulltext opt_key_or_index opt_ident init_key_options ( key_list ) fulltext_key_options' => [IndexKind::FullText, 1, 2, null, 5, 7],
        'key_def: spatial opt_key_or_index opt_ident init_key_options ( key_list ) spatial_key_options' => [IndexKind::Spatial, 1, 2, null, 5, 7],
        'table_constraint_def: key_or_index opt_index_name_and_type ( key_list_with_expression ) opt_index_options' => [IndexKind::Index, null, 1, null, 3, 5],
        'table_constraint_def: FULLTEXT_SYM opt_key_or_index opt_ident ( key_list_with_expression ) opt_fulltext_index_options' => [IndexKind::FullText, 1, 2, null, 4, 6],
        'table_constraint_def: SPATIAL_SYM opt_key_or_index opt_ident ( key_list_with_expression ) opt_spatial_index_options' => [IndexKind::Spatial, 1, 2, null, 4, 6],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an index, key or constraint element: a node of `key_def` or `table_constraint_def`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function element(Node $element): TableElement
    {
        $form = $this->lowering->productions->form($element);
        if (isset(self::INDEXES[$form->signature])) {
            return $this->index($form);
        }
        $references = new ReferenceRule($this->lowering);
        $parts = new KeyPartRule($this->lowering);

        return match ($form->signature) {
            'key_def: opt_constraint constraint_key_type opt_ident key_alg ( key_list ) normal_key_options' => $this->constrained($form, $this->constraint($form->node(0)), $this->lowering->names->optionalColumnName($form->node(2)), (new KeyOptionRule($this->lowering))->algorithm($form->node(3)), 5, 7),
            'table_constraint_def: opt_constraint_name constraint_key_type opt_index_name_and_type ( key_list_with_expression ) opt_index_options' => $this->constrained($form, $this->constraint($form->node(0)), ...(new KeyOptionRule($this->lowering))->nameAndType($form->node(2)), parts: 4, options: 6),
            'key_def: opt_constraint FOREIGN KEY_SYM opt_ident ( key_list ) references',
            'table_constraint_def: opt_constraint_name FOREIGN KEY_SYM opt_ident ( key_list ) references' => new ForeignKey($parts->columns($form->node(5)), $references->references($form->node(7)), $this->lowering->names->optionalColumnName($form->node(3)), $this->constraint($form->node(0))),
            'key_def: opt_constraint check_constraint' => new CheckConstraint($this->check($form->node(1)), $this->constraint($form->node(0))),
            'table_constraint_def: opt_constraint_name check_constraint opt_constraint_enforcement' => new CheckConstraint($this->check($form->node(1)), $this->constraint($form->node(0)), $this->enforcement($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an index without a CONSTRAINT clause, by the positions of INDEXES.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function index(Form $form): IndexDefinition
    {
        [$kind, $keyword, $name, $structure, $parts, $options] = self::INDEXES[$form->signature];
        $rule = new KeyOptionRule($this->lowering);
        $algorithm = null;
        $lead = $form->node->children[0];
        if ($lead instanceof Node) {
            $first = $this->lowering->productions->form($lead);
            match ($first->signature) {
                'normal_key_type: key_or_index' => $this->lowering->options->skip($first->node(0)),
                'key_or_index: KEY_SYM', 'key_or_index: INDEX_SYM' => $this->lowering->options->skip($lead),
                'fulltext: FULLTEXT_SYM', 'spatial: SPATIAL_SYM' => null,
                default => throw ImplementationGap::production($first),
            };
        }
        if ($structure !== null) {
            $algorithm = $rule->algorithm($form->node($structure));
        } elseif ($form->node->children[$parts - 2] instanceof Node && $form->node($parts - 2)->name === 'init_key_options') {
            $this->lowering->options->skip($form->node($parts - 2));
        }
        if ($form->node(1)->name === 'opt_index_name_and_type') {
            [$named, $algorithm] = $rule->nameAndType($form->node(1));
        } else {
            $named = $this->lowering->names->optionalColumnName($form->node($name));
        }

        return new IndexDefinition(
            $kind,
            (new KeyPartRule($this->lowering))->parts($form->node($parts)),
            $named,
            $algorithm,
            $rule->options($form->node($options)),
            null,
            $keyword !== null && $this->lowering->options->present($form->node($keyword)),
        );
    }

    /**
     * Lowers a PRIMARY KEY or UNIQUE index with its optional CONSTRAINT clause.
     *
     * @param ColumnName|null $name The index name
     * @param IndexAlgorithm|null $algorithm The structure clause before the key parts
     * @throws ImplementationGap When a production has no rule
     */
    public function constrained(Form $form, ?ConstraintName $constraint, ?ColumnName $name, ?IndexAlgorithm $algorithm, int $parts, int $options): IndexDefinition
    {
        $type = $this->lowering->productions->form($form->node(1));
        [$kind, $keyword] = match ($type->signature) {
            'constraint_key_type: PRIMARY_SYM KEY_SYM' => [IndexKind::Primary, false],
            'constraint_key_type: UNIQUE_SYM opt_key_or_index' => [IndexKind::Unique, $this->lowering->options->present($type->node(1))],
            default => throw ImplementationGap::production($type),
        };

        return new IndexDefinition($kind, (new KeyPartRule($this->lowering))->parts($form->node($parts)), $name, $algorithm, (new KeyOptionRule($this->lowering))->options($form->node($options)), $constraint, $keyword);
    }

    /**
     * Lowers an optional CONSTRAINT clause: a node of `opt_constraint`, `constraint` or `opt_constraint_name`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function constraint(Node $clause): ?ConstraintName
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_constraint:', 'opt_constraint_name:' => null,
            'opt_constraint: constraint' => $this->constraint($form->node(0)),
            'constraint: CONSTRAINT opt_ident', 'opt_constraint_name: CONSTRAINT opt_ident' => new ConstraintName($this->lowering->names->optionalColumnName($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the condition of a CHECK clause: a node of `check_constraint`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function check(Node $check): Scalar
    {
        $form = $this->lowering->productions->form($check);
        if ($form->signature !== 'check_constraint: CHECK_SYM ( expr )') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->expressions->expression($form->node(2));
    }

    /**
     * Lowers the optional CHECK clause after a 5.x column: a node of `opt_check_constraint`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function optionalCheck(Node $check): ?CheckConstraint
    {
        $form = $this->lowering->productions->form($check);

        return match ($form->signature) {
            'opt_check_constraint:' => null,
            'opt_check_constraint: check_constraint' => new CheckConstraint($this->check($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Tells whether a check constraint is enforced: a node of `constraint_enforcement`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function enforced(Node $enforcement): bool
    {
        $form = $this->lowering->productions->form($enforcement);
        if ($form->signature !== 'constraint_enforcement: opt_not ENFORCED_SYM') {
            throw ImplementationGap::production($form);
        }
        $not = $this->lowering->productions->form($form->node(0));

        return match ($not->signature) {
            'opt_not:' => true,
            'opt_not: NOT_SYM' => false,
            default => throw ImplementationGap::production($not),
        };
    }

    /**
     * Lowers an optional enforcement clause: a node of `opt_constraint_enforcement`; absent is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function enforcement(Node $enforcement): ?bool
    {
        $form = $this->lowering->productions->form($enforcement);

        return match ($form->signature) {
            'opt_constraint_enforcement:' => null,
            'opt_constraint_enforcement: constraint_enforcement' => $this->enforced($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
