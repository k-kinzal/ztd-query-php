<?php

declare(strict_types=1);

namespace SqlSemantics\Testing\Sqlite;

use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\Sqlite\Model\Statement\NamedRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Selection;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\FieldLookup;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Fields;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\UniqueField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;

/**
 * Test fixture for grammar, facts and parser round-trip checks.
 */
final class InspectedStatement
{
    use Snapshot;

    /**
     * @var Facts Every fact derived for the statement against the context
     */
    public readonly Facts $facts;

    private readonly string $sql;

    /**
     * Derives and checks a new root from a context and an explicit statement structure.
     *
     * A structure outside the closed value domain, a node shared between two
     * positions, or a context of another profile is refused as an invalid
     * construction. A missing rule or a failed correspondence check is a defect
     * of the library and discards the candidate.
     *
     * @param AnalysisContext $context The fixed profile and declaration snapshot
     * @param Statement $statement The requested statement
     */
    public function __construct(public readonly AnalysisContext $context, public readonly Statement $statement)
    {
        [$facts, $sql] = (new RenderAudit())->establish($context, $statement);
        $this->facts = $facts;
        $this->sql = $sql;
    }

    /**
     * Derives the binding used by the complete IR lowerer.
     */
    public function binding(): \SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement
    {
        return new \SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement($this->context, $this->statement);
    }

    /**
     * Answers the SQL rendered from the structure and confirmed to correspond to it; never the analyzed text.
     */
    public function toString(): string
    {
        return $this->sql;
    }

    /**
     * Answers the fixed language profile the operation was built under.
     */
    public function profile(): LanguageProfile
    {
        return $this->context->profile;
    }

    /**
     * Answers the relation declarations the statement provides to a context.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        return $this->facts->declarations;
    }

    /**
     * Answers the shape of the rows the statement returns, or null when it returns none.
     */
    public function shape(): ?RowShape
    {
        return $this->facts->output?->shape;
    }

    /**
     * Answers the complete ordered output fields, or null when the statement returns no rows or its shape is open.
     */
    public function fields(): ?Fields
    {
        return $this->facts->output?->fields();
    }

    /**
     * Answers the one field at a position or with a name.
     *
     * This is a convenience for the unique case; use lookupField() to tell
     * absent, ambiguous and dependent lookups apart.
     *
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When no field or more than one field answers the key
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
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When the statement returns no rows
     */
    public function lookupField(string $name): FieldLookup
    {
        Check::input($this->facts->output !== null, 'The statement returns no rows.');

        return $this->facts->output->lookup($name);
    }

    /**
     * Answers the input relation structure of a selection, or null when there is none.
     */
    public function inputRelation(): ?Relation
    {
        return $this->statement instanceof Selection ? $this->statement->input() : null;
    }

    /**
     * Answers the input when it is exactly one plainly named relation.
     *
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When the input is a join, a derived relation, several relations, or absent
     */
    public function singleNamedInput(): NamedRelation
    {
        $input = $this->inputRelation();
        Check::input($input instanceof NamedRelation, 'The statement does not read from exactly one named relation.');

        return $input;
    }
}
