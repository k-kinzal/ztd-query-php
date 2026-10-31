<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TypeNotices;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * A stored CREATE TABLE whose written definition the server executes only when the routine runs.
 *
 * The server reads a routine body with its grammar and checks what it checks while
 * reading: the types, the options and the collations of the definition. Whether the
 * written columns and keys form a table it can create is decided when the instruction
 * is reached; CREATE PROCEDURE accepts a body that creates a table with two columns of one
 * name (verified on 8.4.7 and 5.7.44). This is a program instruction, never a standalone
 * Statement; the executable statement is CreateTable, whose constructor refuses such a
 * definition.
 *
 * @visibility public
 * @example Retaining a definition the routine refuses when it runs
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT)');
 *     $operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable // => true
 */
final class DeferredCreateTable implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<TableElement> The column and key definitions in written order
     */
    public readonly array $elements;

    /**
     * @var list<TableOption> The table options in written order
     */
    public readonly array $options;

    /**
     * @var list<bool> The commas between adjacent options
     */
    public readonly array $optionsCommas;

    /**
     * @param QualifiedName $name The table name, with at most a database qualifier
     * @param list<TableElement> $elements The column and key definitions
     * @param list<TableOption> $options The table options
     * @param Partitioning|null $partitioning The PARTITION BY clause
     * @param TableQuery|null $query The query whose rows fill the table
     * @param int $temporaryWords How many times TEMPORARY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param bool $enclosed Whether the partitioning is written inside the parenthesis that opens the query (MySQL 5.x)
     * @param list<bool>|null $optionsCommas Optional commas between adjacent options
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $elements = [],
        array $options = [],
        public readonly ?Partitioning $partitioning = null,
        public readonly ?TableQuery $query = null,
        public readonly int $temporaryWords = 0,
        public readonly bool $ifNotExists = false,
        public readonly bool $enclosed = false,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        ?array $optionsCommas = null,
    ) {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($name);
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($temporaryWords >= 0, 'TEMPORARY is written a non-negative number of times.');
        $this->elements = Check::listOf($elements, TableElement::class, 'Table elements are an ordered list of table elements.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
        $this->optionsCommas = (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->checked($optionsCommas, count($this->options), false);
        Check::input(
            !$enclosed || ($this->elements === [] && $this->options === [] && $partitioning !== null && $query !== null && $query->duplicate === null && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery())->accepts($query->query)),
            'Only a partitioning and a query that starts with a parenthesized query are written enclosed.',
        );
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Tells whether TEMPORARY is written.
     */
    public function temporary(): bool
    {
        return $this->temporaryWords > 0;
    }

    /**
     * Derives what the server checks while it reads the body: the types, options and collations of the
     * definition, and the expressions and query it holds, without a table to resolve names against.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $facts = new ElementFacts();
        $environment = new \SqlSemantics\Resolution\Environment($derivation->context, null, []);
        foreach ($this->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                (new TypeNotices())->type($element->specification->dataType(), $derivation);
                (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->check($element->specification->dataType(), $element->name->column->value, $derivation, $element);
            } elseif ($element instanceof IndexDefinition) {
                (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions())->parsers($element->options, $derivation);
            }
            $facts->element($element, $derivation, $environment);
        }
        $facts->options($this->options, $derivation);
        (new TableProblems())->collations($this->elements, $derivation);
        $this->partitioning?->derivePartitioning($derivation, $environment);
        if ($this->query?->query instanceof ProgramStatement) {
            $this->query->query->deriveProgram($derivation, $scope);
        } elseif ($this->query !== null) {
            $derivation->query($this->query->query, $derivation->environment());
        }
    }

    /**
     * Writes the stored definition.
     */
    public function render(Output $out): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Rendering\TableDefinitionText())->render($out, $this->name, $this->temporaryWords, $this->ifNotExists, $this->elements, $this->options, $this->optionsCommas, $this->partitioning, $this->query, $this->enclosed);
    }
}
