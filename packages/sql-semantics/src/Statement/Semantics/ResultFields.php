<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Selection;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\FieldLookup;
use SqlSemantics\Statement\Shape\Fields;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Shape\UniqueField;

/**
 * Result-column access on the concrete statement that produces those columns.
 *
 * @visibility SqlSemantics
 */
trait ResultFields
{
    /**
 * Returns the result of this statement, if it produces rows.
 */
    abstract public function output(): ?QueryFact;

    /**
     * Answers the shape of the rows the statement returns, or null when it returns none.
     */
    public function shape(): ?RowShape
    {
        return $this->output()?->shape;
    }

    /**
     * Answers the complete ordered output fields, or null when the statement returns no rows or its shape is open.
     */
    public function fields(): ?Fields
    {
        return $this->output()?->fields();
    }

    /**
     * Answers the one field at a position or with a name.
     *
     * This is a convenience for the unique case; use lookupField() to tell
     * absent, ambiguous and dependent lookups apart.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When no field or more than one field answers the key
     */
    public function field(string|int $key): Field
    {
        if (is_int($key)) {
            $fields = $this->fields();
            Check::input($fields !== null, 'The statement has no complete field list.');

            return $fields->at($key);
        }
        $lookup = $this->lookupField($key);
        Check::input($lookup instanceof UniqueField, 'The name does not denote exactly one output field.');

        return $lookup->field;
    }

    /**
     * Looks an output field up by name, distinguishing unique, absent, ambiguous and dependent results.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the statement returns no rows
     */
    public function lookupField(string $name): FieldLookup
    {
        Check::input($this->output() !== null, 'The statement returns no rows.');

        return $this->output()->lookup($name);
    }

    /**
     * Answers the input relation structure of a selection, or null when there is none.
     */
    public function inputRelation(): ?Relation
    {
        return $this instanceof Selection ? $this->input() : null;
    }

    /**
     * Answers the input when it is exactly one plainly named relation.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the input is a join, a derived relation, several relations, or absent
     */
    public function singleNamedInput(): NamedRelation
    {
        $input = $this->inputRelation();
        Check::input($input instanceof NamedRelation, 'The statement does not read from exactly one named relation.');

        return $input;
    }
}
