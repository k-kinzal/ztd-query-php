<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NullAllowed;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

/**
 * Lowers column definitions and their constraints.
 *
 * Rule: SQLITE-COLUMN-LOWER-001. Scope: columnname, carglist, ccons,
 * generated, autoinc, scantok. Constructors: ColumnDefinition and the column
 * constraint classes. The constraints keep their written order; a
 * `CONSTRAINT name` clause is a list element of its own, as in the grammar.
 * The keywords GENERATED ALWAYS before a generated column expression are
 * declared noise; every other token is a model value. Terminates: the
 * constraint list is flattened iteratively and every constraint is a strict
 * subtree. Source: https://sqlite.org/syntax/column-def.html,
 * https://sqlite.org/syntax/column-constraint.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ColumnRule
{
    private readonly ForeignKeyRule $foreignKeys;

    private readonly WordRule $words;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->foreignKeys = new ForeignKeyRule($lowering);
        $this->words = new WordRule($lowering);
    }

    /**
     * Lowers a column name with its type and the constraint list written after it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $name, Node $constraints): ColumnDefinition
    {
        $form = $this->lowering->productions->form($name);
        if ($form->signature !== 'columnname: nm typetoken') {
            throw ImplementationGap::production($form);
        }
        $column = $this->lowering->names->name($form->node(0));
        $type = $this->lowering->typeNames->named($form->node(1));

        return new ColumnDefinition($column, $type, $this->constraints($constraints));
    }

    /**
     * Lowers the constraints of a column in written order.
     *
     * @return list<ColumnConstraint>
     * @throws ImplementationGap When a production has no rule
     */
    public function constraints(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'carglist:' && $form->signature !== 'carglist: carglist ccons') {
            throw ImplementationGap::production($form);
        }
        $constraints = [];
        foreach ((new Lists())->items($list) as $item) {
            $constraints[] = $this->constraint($this->lowering->productions->form($item));
        }

        return $constraints;
    }

    /**
     * Lowers one column constraint.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraint(Form $form): ColumnConstraint
    {
        return match ($form->signature) {
            'ccons: CONSTRAINT nm' => new ConstraintName($this->lowering->names->name($form->node(1))),
            'ccons: NULL onconf' => new NullAllowed($this->lowering->conflicts->onConflict($form->node(1))),
            'ccons: NOT NULL onconf' => new NotNull($this->lowering->conflicts->onConflict($form->node(2))),
            'ccons: PRIMARY KEY sortorder onconf autoinc' => new ColumnPrimaryKey(
                $this->lowering->ordering->direction($form->node(2)),
                $this->lowering->conflicts->onConflict($form->node(3)),
                $this->autoincrement($form->node(4)),
            ),
            'ccons: UNIQUE onconf' => new ColumnUnique($this->lowering->conflicts->onConflict($form->node(1))),
            'ccons: CHECK LP expr RP' => new ColumnCheck($this->lowering->expressions->expression($form->node(2))),
            'ccons: REFERENCES nm eidlist_opt refargs' => $this->foreignKeys->clause($form->node(1), $form->node(2), $form->node(3)),
            'ccons: defer_subclause' => $this->foreignKeys->deferrability($form->node(0)),
            'ccons: COLLATE ids' => new Collation($this->lowering->names->token($form->token(1))),
            'ccons: GENERATED ALWAYS AS generated' => $this->generated($form->node(3)),
            'ccons: AS generated' => $this->generated($form->node(1)),
            default => $this->default($form),
        };
    }

    /**
     * Lowers a DEFAULT constraint.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function default(Form $form): ColumnConstraint
    {
        return match ($form->signature) {
            'ccons: DEFAULT scantok term' => new DefaultLiteral($this->lowering->expressions->term($this->scanned($form->node(1), $form->node(2)))),
            'ccons: DEFAULT PLUS scantok term' => new DefaultLiteral($this->lowering->expressions->term($this->scanned($form->node(2), $form->node(3))), NumberSign::Plus),
            'ccons: DEFAULT MINUS scantok term' => new DefaultLiteral($this->lowering->expressions->term($this->scanned($form->node(2), $form->node(3))), NumberSign::Minus),
            'ccons: DEFAULT LP expr RP' => new DefaultExpression($this->lowering->expressions->expression($form->node(2))),
            'ccons: DEFAULT scantok id' => new DefaultWord($this->words->word($this->scanned($form->node(1), $form->token(2)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Checks the empty marker the grammar places before a default value and answers what follows it.
     *
     * @template T of object
     * @param T $following
     * @return T
     * @throws ImplementationGap When the production has no rule
     */
    public function scanned(Node $marker, object $following): object
    {
        $form = $this->lowering->productions->form($marker);
        if ($form->signature !== 'scantok:') {
            throw ImplementationGap::production($form);
        }

        return $following;
    }

    /**
     * Lowers the optional AUTOINCREMENT keyword.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function autoincrement(Node $keyword): bool
    {
        $form = $this->lowering->productions->form($keyword);

        return match ($form->signature) {
            'autoinc:' => false,
            'autoinc: AUTOINCR' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the expression of a generated column and the optional word after it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function generated(Node $generated): Generated
    {
        $form = $this->lowering->productions->form($generated);

        return match ($form->signature) {
            'generated: LP expr RP' => new Generated($this->lowering->expressions->expression($form->node(1))),
            'generated: LP expr RP ID' => new Generated($this->lowering->expressions->expression($form->node(1)), $this->words->word($form->token(3))),
            default => throw ImplementationGap::production($form),
        };
    }
}
