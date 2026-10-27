<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Notice;

/**
 * A SQL statement whose semantic values provide its reconstruction.
 *
 * @visibility public
 * @example An analysis result is a concrete SQL statement
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT t VALUES ('42')");
 *     [$insert instanceof \SqlSemantics\Statement\AnalyzedStatement, $insert->toString()] // => [true, "INSERT t VALUES ('42')"]
 */
interface AnalyzedStatement extends Statement
{
    /**
     * Reconstructs SQL from the statement's values, without input text or a parser tree.
     */
    public function toString(): string;

    /**
     * Answers the language the statement belongs to.
     */
    public function profile(): LanguageProfile;

    /**
     * Answers the declarations a definition statement contributes to analysis.
     *
     * @return list<Table>
     */
    public function declarations(): array;

    /**
     * Returns semantic conditions that prevent this statement from executing.
     *
     * @return list<Fact\Diagnostic>
     */
    public function diagnostics(): array;

    /**
     * Returns nonfatal conditions in their emission order.
     *
     * @return list<Fact\Warning>
     */
    public function warnings(): array;

    /**
     * Returns warnings together with their owning SQL occurrences.
     *
     * @return list<Notice>
     */
    public function notices(): array;
    /**
 * Returns the result of this statement, if it produces rows.
 */
    public function output(): ?Fact\QueryFact;

    /**
 * Returns the result shape, including unresolved stars.
 */
    public function shape(): ?Shape\RowShape;

    /**
 * Returns all result columns when the shape is complete.
 */
    public function fields(): ?Shape\Fields;

    /**
 * Returns one unambiguous result column.
 */
    public function field(string|int $key): Shape\Field;

    /**
 * Looks up a result name, retaining absence, ambiguity and dependencies.
 */
    public function lookupField(string $name): Shape\FieldLookup;

    /**
 * Returns the input relation of a selection.
 */
    public function inputRelation(): ?Relation;

    /**
 * Returns the one plainly named input, refusing other input structures.
 */
    public function singleNamedInput(): NamedRelation;
    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasStatementInformation(): bool;
}
