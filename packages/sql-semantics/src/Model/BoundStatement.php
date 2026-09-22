<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * A bound SQL statement: sources, targets, row conditions, ordered outputs, and modifiers.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->scopeId // => 's0'
 *
 * @phpstan-consistent-constructor
 * @visibility public
 */
abstract class BoundStatement
{
    /**
     * Complete SQL structure, without source whitespace or ordinary comments.
     */
    public readonly Sql\Tree $sql;

    /**
     * @visibility SqlSemantics
     * @param string $scopeId Stable scope identity within this bound statement
     * @param TableUse|Join|null $from Logical input, absent for a constant SELECT
     * @param list<TableUse> $relations Table occurrences in binding order
     * @param list<OutputColumn> $outputs Ordered result columns
     * @param Expression|null $where Predicate applied after joins; only TRUE retains a row
     * @param bool $distinct Whether duplicate output tuples are removed
     * @param list<Ordering> $orderBy Ordered sort keys
     * @param Expression|null $limit Maximum row count expression
     * @param Expression|null $offset Number of rows to skip
     * @param Node $source Original statement tree
     * @param list<Expression> $groupBy Grouping expressions
     * @param Expression|null $having Group filter
     * @param array<int|string, BoundStatement> $ctes Local common table expressions
     * @param list<BoundQuery> $branches Compound query operands
     * @param string|null $setOperator Compound operation, including ALL
     * @param array<string, list<Expression>> $clauses Additional expression-bearing clauses
     * @param string $kind Statement operation
     * @param list<TableUse> $targets Written or affected relations
     * @param array<int|string, Expression> $assignments Assigned column values
     * @param list<BoundQuery> $queries Input queries
     * @param list<list<Expression>> $rows Explicit VALUES rows
     * @param bool $withTies Include peers of the final ordered row
     * @param array<string, list<Node>> $syntaxClauses Complete clauses, including non-expression modifiers
     * @param list<\SqlSemantics\Schema\TableDefinition> $declarations Table declarations defined by this statement
     * @param Write\Insertion|null $insertion Ordered INSERT input destinations
     * @param list<Write\Assignment> $writes Ordered assignments, including tuple destinations
     * @param list<Configuration\Setting> $settings Ordered configuration effects
     * @param list<Write\ConflictAction> $conflicts Ordered conflict handlers
     * @param list<Definition\TableDeclaration> $definitions Bound declaration expressions by role
     * @param Write\Merge|null $merge Conditional write plan
     * @param list<BoundStatement> $statements Nested commands, such as an explained or prepared statement
     * @param list<Diagnostic> $diagnostics Semantic problems from binding this statement, including nested scopes
     * @param list<Definition\IndexDeclaration> $indexes Index definitions with bound keys and predicates
     */
    public function __construct(
        public readonly string $scopeId,
        public readonly TableUse|Join|null $from,
        public readonly array $relations,
        public readonly array $outputs,
        public readonly ?Expression $where,
        public readonly bool $distinct,
        public readonly array $orderBy,
        public readonly ?Expression $limit,
        public readonly ?Expression $offset,
        public readonly Node $source,
        public readonly array $groupBy = [],
        public readonly ?Expression $having = null,
        public readonly array $ctes = [],
        public readonly array $branches = [],
        public readonly ?string $setOperator = null,
        public readonly array $clauses = [],
        public readonly string $kind = 'SELECT',
        public readonly array $targets = [],
        public readonly array $assignments = [],
        public readonly array $queries = [],
        public readonly array $rows = [],
        public readonly bool $withTies = false,
        public readonly array $syntaxClauses = [],
        public readonly array $declarations = [],
        public readonly ?Write\Insertion $insertion = null,
        public readonly array $writes = [],
        public readonly array $settings = [],
        public readonly array $conflicts = [],
        public readonly array $definitions = [],
        public readonly ?Write\Merge $merge = null,
        public readonly array $statements = [],
        public readonly array $diagnostics = [],
        public readonly array $indexes = [],
        ?Sql\Tree $sql = null,
        private readonly ?Transformation\Context $context = null,
    ) {
        $this->sql = $sql ?? Sql\Source::read($source);
        Validation\StatementInvariant::check($this);
    }

    /**
     * Writes the statement structure using the standard compact SQL layout.
     */
    public function toString(): string
    {
        return $this->sql->toString();
    }

    /**
     * Attaches the semantic validator used for all changes to this snapshot.
     *
     * @visibility SqlSemantics
     */
    public function withContext(Transformation\Context $context): static
    {
        return $this->copy($context, $this->outputs);
    }

    /**
     * Replaces one expression by a structured value and validates the complete result.
     *
     * @throws Validation\InvalidStructure
     */
    public function replaceExpression(Expression $target, Expression $replacement): static
    {
        if (!in_array($target, Traversal\Expressions::all($this), true)) {
            throw new Validation\InvalidStructure('The expression does not belong to this statement.');
        }
        if ($replacement->type->dialect !== $this->context()->schema()->dialect) {
            throw new Validation\InvalidStructure('A replacement expression must use the statement dialect.');
        }
        $owner = Traversal\Expressions::projection($this, $target);
        if ($owner !== null && $target->kind === ExpressionKind::Column && in_array('*', array_column($target->source instanceof \SqlParser\Lexer\Token ? [$target->source] : $target->source->tokens(), 'text'), true)) {
            $outputs = array_map(static fn (OutputColumn $output): OutputColumn => new OutputColumn($output->ordinal, $output->name, $output->expression === $target ? $replacement : $output->expression), $owner->outputs);
            return $this->component($owner->source, $owner->withOutputs($outputs)->sql);
        }
        $tree = Sql\Build::parentheses($replacement->sql);
        foreach ($this->settings as $setting) {
            if (in_array($target, $setting->values, true)) {
                $tree = $replacement->sql;
            }
        }
        return $this->changed(Transformation\SourceEdit::replace($this->source, $this->sql, $target->source, $tree));
    }

    /**
     * @visibility SqlSemantics
     * @throws Validation\InvalidStructure
     */
    protected function context(): Transformation\Context
    {
        if ($this->context === null) {
            throw new Validation\InvalidStructure('A statement transformation requires its schema context.');
        }
        return $this->context;
    }

    /**
     * Validates the complete structure and refreshes names, types, and dependencies.
     */
    protected function changed(Sql\Tree $sql): static
    {
        return $this->context()->rebind($this, $sql);
    }


    /**
     * Replaces an owned SQL component and validates the resulting statement.
     * This also covers syntax specific to one database release.
     */
    public function replaceStructure(Sql\Tree $target, Sql\Tree $replacement): static
    {
        return $this->changed(Transformation\TreeEdit::replace($this->sql, $target, $replacement));
    }

    /**
     * Replaces a component at its grammar-defined position in this statement.
     */
    protected function clause(string $role, Sql\Tree $replacement): static
    {
        return $this->context()->clause($this, $role, $replacement);
    }

    /**
     * Replaces an owned parsed component using structure and refreshes all dependent facts.
     */
    protected function component(Node|\SqlParser\Lexer\Token $target, Sql\Tree $replacement): static
    {
        return $this->changed(Transformation\SourceEdit::replace($this->source, $this->sql, $target, $replacement));
    }

    /**
     * Applies names introduced by a relation boundary, such as a CTE column list.
     * @param list<string> $names
     * @visibility SqlSemantics
     */
    public function withOutputNames(array $names): static
    {
        Validation\Collections::strings($names);
        $outputs = [];
        foreach ($this->outputs as $ordinal => $output) {
            $outputs[] = new OutputColumn($ordinal, $names[$ordinal] ?? $output->name, $output->expression);
        }
        return $this->copy($this->context, $outputs);
    }

    /**
     * @param list<OutputColumn> $outputs
     */
    protected function copy(?Transformation\Context $context, array $outputs): static
    {
        return new static(
            scopeId: $this->scopeId, from: $this->from, relations: $this->relations,
            outputs: $outputs, where: $this->where, distinct: $this->distinct,
            orderBy: $this->orderBy, limit: $this->limit, offset: $this->offset,
            source: $this->source, groupBy: $this->groupBy, having: $this->having,
            ctes: $this->ctes, branches: $this->branches, setOperator: $this->setOperator,
            clauses: $this->clauses, kind: $this->kind, targets: $this->targets,
            assignments: $this->assignments, queries: $this->queries, rows: $this->rows,
            withTies: $this->withTies, syntaxClauses: $this->syntaxClauses,
            declarations: $this->declarations, insertion: $this->insertion,
            writes: $this->writes, settings: $this->settings, conflicts: $this->conflicts,
            definitions: $this->definitions, merge: $this->merge, statements: $this->statements,
            diagnostics: $this->diagnostics, indexes: $this->indexes, sql: $this->sql, context: $context,
        );
    }
}
