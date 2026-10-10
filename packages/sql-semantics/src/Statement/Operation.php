<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\FieldLookup;
use SqlSemantics\Statement\Shape\Fields;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Shape\UniqueField;
use SqlSemantics\Validation\Publication;

/**
 * One analyzed statement: its structure, the facts derived against a fixed context, and its SQL.
 *
 * The constructor is the only way to obtain an operation and it is a checking
 * boundary. It audits the structure, derives every fact, renders the SQL, and
 * confirms that the rendered SQL has the same structure. Nothing is filled in
 * afterwards and nothing can be changed. A different statement is a new
 * operation built from explicit inputs or from newly analyzed SQL; it has no
 * guaranteed relation to this one.
 *
 * @visibility public
 * @example Analyzing against the declaration another statement provides
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $bar = $semantics->analyze('CREATE TABLE bar (foo INTEGER, baz TEXT)');
 *     $query = $semantics->analyze('SELECT foo FROM bar', [$bar]);
 *     [$query->field('foo')->column() === $bar->declarations()[0]->columns[0], $query->toString()] // => [true, 'SELECT foo FROM bar']
 * @example Building a new root from explicit structure
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $query = $semantics->analyze('SELECT foo FROM bar WHERE foo > 1');
 *     $other = new \SqlSemantics\Statement\Operation($query->context, $semantics->analyze('SELECT baz FROM bar')->statement);
 *     $other->toString() // => 'SELECT baz FROM bar'
 */
final class Operation
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
     * @param Source\SourceMap $sources Optional original input locations, independent of the semantic structure
     */
    public function __construct(public readonly AnalysisContext $context, public readonly Statement $statement, public readonly Source\SourceMap $sources = new Source\SourceMap())
    {
        [$facts, $sql] = (new Publication())->establish($context, $statement, $sources);
        $this->facts = $facts;
        $this->sql = $sql;
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
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the input is a join, a derived relation, several relations, or absent
     */
    public function singleNamedInput(): NamedRelation
    {
        $input = $this->inputRelation();
        Check::input($input instanceof NamedRelation, 'The statement does not read from exactly one named relation.');

        return $input;
    }
}
