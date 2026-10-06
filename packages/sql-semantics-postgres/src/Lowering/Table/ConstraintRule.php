<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\Exclusion;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ExclusionElement;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\ForeignKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers table constraints, constraint attributes and foreign-key clauses.
 *
 * Rule: PG-CONSTRAINT-LOWER-001. Scope: `TableConstraint`, `ConstraintElem`,
 * `opt_c_include`, `ExclusionConstraintList`, `ExclusionConstraintElem`,
 * `OptConsTableSpace`, `ExistingIndex`, `ConstraintAttributeSpec`,
 * `ConstraintAttributeElem`, `key_match`, `key_actions`, `key_update`,
 * `key_delete`, `key_action`. A constraint name is kept on its constraint;
 * attributes and referential actions keep the order written. Termination:
 * lists are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ConstraintRule
{
    /**
     * The attribute each `ConstraintAttributeElem` production writes.
     */
    private const ATTRIBUTES = [
        'ConstraintAttributeElem: NOT DEFERRABLE' => ConstraintAttribute::NotDeferrable,
        'ConstraintAttributeElem: DEFERRABLE' => ConstraintAttribute::Deferrable,
        'ConstraintAttributeElem: INITIALLY IMMEDIATE' => ConstraintAttribute::InitiallyImmediate,
        'ConstraintAttributeElem: INITIALLY DEFERRED' => ConstraintAttribute::InitiallyDeferred,
        'ConstraintAttributeElem: NOT VALID' => ConstraintAttribute::NotValid,
        'ConstraintAttributeElem: NO INHERIT' => ConstraintAttribute::NoInherit,
    ];

    /**
     * The match type each `key_match` production writes.
     */
    private const MATCH = ['key_match: MATCH FULL' => KeyMatch::Full, 'key_match: MATCH PARTIAL' => KeyMatch::Partial, 'key_match: MATCH SIMPLE' => KeyMatch::Simple];

    /**
     * The action each `key_action` production writes.
     */
    private const ACTIONS = [
        'key_action: NO ACTION' => ReferenceAction::NoAction, 'key_action: RESTRICT' => ReferenceAction::Restrict,
        'key_action: CASCADE' => ReferenceAction::Cascade, 'key_action: SET NULL_P opt_column_list' => ReferenceAction::SetNull,
        'key_action: SET DEFAULT opt_column_list' => ReferenceAction::SetDefault,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `TableConstraint`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tableConstraint(Node $constraint): Constraint
    {
        $form = $this->lowering->productions->form($constraint);

        return match ($form->signature) {
            'TableConstraint: CONSTRAINT name ConstraintElem' => $this->element($this->lowering->productions->form($form->node(2)), $this->lowering->names->name($form->node(1))),
            'TableConstraint: ConstraintElem' => $this->element($this->lowering->productions->form($form->node(0)), null),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ConstraintElem` with its name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function element(Form $form, ?Name $name): Constraint
    {
        $names = $this->lowering->names;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'ConstraintElem: CHECK ( a_expr ) ConstraintAttributeSpec' => new TableCheck($this->lowering->expressions->expression($form->node(2)), $this->attributes($form->node(4)), $name),
            'ConstraintElem: UNIQUE opt_unique_null_treatment ( columnList ) opt_c_include opt_definition OptConsTableSpace ConstraintAttributeSpec' => new TableUnique(
                $names->names($form->node(3)),
                $this->lowering->tables->uniqueNullTreatment($form->node(1)),
                $this->included($form->node(5)),
                $options->definitions($form->node(6)),
                $this->tablespace($form->node(7)),
                $this->attributes($form->node(8)),
                $name,
            ),
            'ConstraintElem: UNIQUE ExistingIndex ConstraintAttributeSpec' => new IndexConstraint(false, $this->index($form->node(1)), $this->attributes($form->node(2)), $name),
            'ConstraintElem: PRIMARY KEY ( columnList ) opt_c_include opt_definition OptConsTableSpace ConstraintAttributeSpec' => new TablePrimaryKey(
                $names->names($form->node(3)),
                $this->included($form->node(5)),
                $options->definitions($form->node(6)),
                $this->tablespace($form->node(7)),
                $this->attributes($form->node(8)),
                $name,
            ),
            'ConstraintElem: PRIMARY KEY ExistingIndex ConstraintAttributeSpec' => new IndexConstraint(true, $this->index($form->node(2)), $this->attributes($form->node(3)), $name),
            'ConstraintElem: EXCLUDE access_method_clause ( ExclusionConstraintList ) opt_c_include opt_definition OptConsTableSpace OptWhereClause ConstraintAttributeSpec' => new Exclusion(
                $this->exclusions($form->node(3)),
                (new IndexRule($this->lowering))->method($form->node(1)),
                $this->included($form->node(5)),
                $options->definitions($form->node(6)),
                $this->tablespace($form->node(7)),
                (new IndexRule($this->lowering))->predicate($form->node(8)),
                $this->attributes($form->node(9)),
                $name,
            ),
            'ConstraintElem: FOREIGN KEY ( columnList ) REFERENCES qualified_name opt_column_list key_match key_actions ConstraintAttributeSpec' => new ForeignKey(
                $names->names($form->node(3)),
                $names->qualified($form->node(6)),
                $names->names($form->node(7)),
                $this->match($form->node(8)),
                $this->actions($form->node(9)),
                $this->attributes($form->node(10)),
                $name,
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_c_include`.
     *
     * @return list<Name>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function included(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_c_include: INCLUDE ( columnList )' => $this->lowering->names->names($form->node(2)),
            'opt_c_include:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ExclusionConstraintList`.
     *
     * @return list<ExclusionElement>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function exclusions(Node $list): array
    {
        $elements = [];
        $index = new IndexRule($this->lowering);
        foreach ($this->lowering->items($list, 'ExclusionConstraintList: ExclusionConstraintElem', 'ExclusionConstraintList: ExclusionConstraintList , ExclusionConstraintElem') as $item) {
            $form = $this->lowering->productions->form($item);
            $elements[] = match ($form->signature) {
                'ExclusionConstraintElem: index_elem WITH any_operator' => new ExclusionElement($index->element($form->node(0)), $this->lowering->operators->operator($form->node(2))),
                'ExclusionConstraintElem: index_elem WITH OPERATOR ( any_operator )' => new ExclusionElement($index->element($form->node(0)), $this->lowering->operators->qualified($form->node(4), true)),
                default => throw ImplementationGap::production($form),
            };
        }

        return $elements;
    }

    /**
     * Lowers `OptConsTableSpace`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tablespace(Node $clause): ?Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptConsTableSpace: USING INDEX TABLESPACE name' => $this->lowering->names->name($form->node(3)),
            'OptConsTableSpace:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ExistingIndex`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function index(Node $clause): Name
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature !== 'ExistingIndex: USING INDEX name') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->name($form->node(2));
    }

    /**
     * Lowers `ConstraintAttributeSpec`.
     *
     * @return list<ConstraintAttribute>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function attributes(Node $list): array
    {
        $attributes = [];
        foreach ($this->lowering->items($list, 'ConstraintAttributeSpec:', 'ConstraintAttributeSpec: ConstraintAttributeSpec ConstraintAttributeElem') as $item) {
            $form = $this->lowering->productions->form($item);
            $attributes[] = self::ATTRIBUTES[$form->signature] ?? throw ImplementationGap::production($form);
        }

        return $attributes;
    }

    /**
     * Lowers `key_match`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function match(Node $clause): ?KeyMatch
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'key_match:') {
            return null;
        }

        return self::MATCH[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `key_actions` with its `key_update` and `key_delete`.
     *
     * @return list<ReferentialAction>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function actions(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        $actions = [];
        foreach ($form->node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            $event = $this->lowering->productions->form($child);
            $actions[] = match ($event->signature) {
                'key_update: ON UPDATE key_action' => $this->action(ReferenceEvent::Update, $event->node(2)),
                'key_delete: ON DELETE_P key_action' => $this->action(ReferenceEvent::Delete, $event->node(2)),
                default => throw ImplementationGap::production($event),
            };
        }
        $known = ['key_actions: key_update', 'key_actions: key_delete', 'key_actions: key_update key_delete', 'key_actions: key_delete key_update', 'key_actions:'];
        if (!in_array($form->signature, $known, true)) {
            throw ImplementationGap::production($form);
        }

        return $actions;
    }

    /**
     * Lowers `key_action` for an event.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function action(ReferenceEvent $event, Node $action): ReferentialAction
    {
        $form = $this->lowering->productions->form($action);
        $kind = self::ACTIONS[$form->signature] ?? throw ImplementationGap::production($form);

        return new ReferentialAction($event, $kind, $kind->setsColumns() ? $this->lowering->names->names($form->node(2)) : []);
    }
}
