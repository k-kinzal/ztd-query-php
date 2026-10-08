<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Family\Calls;
use MySqlMemory\Evaluation\Compile\Family\Casts;
use MySqlMemory\Evaluation\Compile\Family\Dates;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Compile\Family\Texts;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Typing\Domain;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\CharCall;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * Compiles the expressions of a bound statement into evaluables: the resolving step of the server, done for each node once.
 *
 * Each node is read with the facts SQL Semantics derived for it: the column a name resolves to,
 * the select item an alias names. An expression form the emulator does not evaluate is refused
 * with ER_NOT_SUPPORTED_YET.
 *
 * @visibility MySqlMemory
 */
final class Compiler
{
    /**
     * Compiles the literals of the statement.
     */
    public readonly Literals $literals;

    /**
     * Compiles the operators and predicates of the statement.
     */
    public readonly Operators $operators;

    /**
     * Compiles the columns, select items, parameters and variables of the statement.
     */
    public readonly Names $names;

    /**
     * Compiles the calls of built-in functions of the statement.
     */
    public readonly Calls $calls;

    /**
     * Compiles the subqueries of the statement used as values and in predicates.
     */
    public readonly Subqueries $subqueries;

    /**
     * Compiles the date arithmetic of the statement.
     */
    public readonly Dates $dates;

    /**
     * Compiles the string forms of the statement written with keywords.
     */
    public readonly Texts $texts;

    /**
     * Compiles the row comparisons of the statement.
     */
    public readonly Rows $rows;

    /**
     * Compiles the JSON operators of the statement.
     */
    public readonly Jsons $jsons;

    /**
     * @var array<int, int>|null The index of each parameter marker of the statement, by object id
     */
    private ?array $parameters = null;

    /**
     * @var list<string>|null The lower-case names of the user variables the statement assigns
     */
    private ?array $assigned = null;

    /**
     * @param Facts $facts The facts of the bound statement
     * @param Settings $settings The session settings
     * @param Planner $planner The planner of the statement, for subqueries
     * @param Connection $connection What the expressions read of the connection at compile time
     */
    public function __construct(public readonly Facts $facts, public readonly Settings $settings, public readonly Planner $planner, public readonly Connection $connection)
    {
        $this->literals = new Literals($this);
        $this->operators = new Operators($this);
        $this->names = new Names($this);
        $this->calls = new Calls($this);
        $this->subqueries = new Subqueries($this);
        $this->dates = new Dates($this);
        $this->texts = new Texts($this);
        $this->rows = new Rows($this);
        $this->jsons = new Jsons($this);
    }

    /**
     * Answers how long the value of an expression of the statement stays the same.
     *
     * A user variable the statement assigns anywhere varies by row.
     *
     * @param bool $correlation Whether a correlated subquery varies by row
     * @param bool $assignmentsVary Whether an assignment to a user variable varies by row; when false, it stays as long as the value it assigns
     */
    public function constancy(Scalar $node, bool $correlation = true, bool $assignmentsVary = true): Constancy
    {
        if ($this->assigned === null) {
            $this->assigned = array_values(array_unique(array_map(static fn (VariableAssignment $assignment): string => strtolower($assignment->target->name->value), (new Walker())->find($this->planner->statement, VariableAssignment::class))));
        }

        return Constancy::of($node, $this->facts, $correlation, $this->assigned, $assignmentsVary);
    }

    /**
     * Answers the index of a parameter marker among the markers of the statement, in written order.
     */
    public function parameterIndex(Parameter $parameter): int
    {
        if ($this->parameters === null) {
            $this->parameters = [];
            foreach ((new Walker())->find($this->planner->statement, Parameter::class) as $index => $marker) {
                $this->parameters[spl_object_id($marker)] = $index;
            }
        }

        return $this->parameters[spl_object_id($parameter)] ?? 0;
    }

    /**
     * Compiles an expression in the scope of its query block.
     *
     * @throws \MySqlMemory\Error\SqlError When the expression is refused
     */
    public function compile(Scalar $node, Scope $scope): Evaluable
    {
        $bound = $scope->bound($node);
        if ($bound !== null) {
            return $bound[0] === 0 ? $bound[1] : $this->names->outer($bound[1], $bound[0]);
        }

        return $this->typed($node, $this->dispatch($node, $scope));
    }

    /**
     * Answers the type SQL Semantics resolved for a node, with the nullability it derived, or null when it resolved only the class of the type.
     */
    public function resolved(Scalar $node): ?Domain
    {
        if (!$this->facts->covers($node)) {
            return null;
        }
        $fact = $this->facts->scalar($node);
        $type = $fact->type;
        if ($type instanceof NullOnly) {
            return Domain::null();
        }
        if (!$type instanceof Known || !$type->descriptor instanceof Resolved) {
            return null;
        }

        return Domain::of($type->descriptor, $fact->nullability !== Nullability::NotNull);
    }

    /**
     * Answers the type SQL Semantics resolved for a node.
     *
     * @throws \MySqlMemory\Error\SqlError When SQL Semantics resolved only the class of its type
     */
    public function domain(Scalar $node): Domain
    {
        return $this->resolved($node) ?? throw StatementError::NotSupportedYet->error('the type of ' . (new ReflectionClass($node))->getShortName());
    }

    /**
     * Gives a compiled expression the type SQL Semantics resolved for its node, when it resolved one.
     */
    public function typed(Scalar $node, Evaluable $evaluable): Evaluable
    {
        if (!$this->facts->covers($node)) {
            return $evaluable;
        }
        $fact = $this->facts->scalar($node);
        $nullable = match ($fact->nullability) {
            Nullability::NotNull => false,
            Nullability::Nullable => true,
            Nullability::Dependent => $evaluable->domain()->nullable,
        };
        $type = $fact->type;
        $domain = $type instanceof Known && $type->descriptor instanceof Resolved ? Domain::of($type->descriptor, $nullable)->withNumericBytes($evaluable->domain()->numericBytes && $type->descriptor->kind === $evaluable->domain()->kind) : $evaluable->domain()->withNullable($nullable);

        return $domain === $evaluable->domain() ? $evaluable : new Retyped($evaluable, $domain);
    }

    /**
     * Compiles a node by its form, before it is given the type SQL Semantics resolved for it.
     *
     * @throws \MySqlMemory\Error\SqlError When the form is one the emulator does not evaluate, or is not valid where it is written
     */
    public function dispatch(Scalar $node, Scope $scope): Evaluable
    {
        return $this->compileLiteral($node, $scope)
            ?? $this->compileName($node, $scope)
            ?? $this->compileOperator($node, $scope)
            ?? $this->compileText($node, $scope)
            ?? $this->compileCall($node, $scope)
            ?? $this->compileSubquery($node, $scope)
            ?? throw StatementError::NotSupportedYet->error('expression ' . (new ReflectionClass($node))->getShortName());
    }

    /**
     * Compiles a literal, an ODBC escape or a parameter marker, or answers null for a node of another form.
     *
     * @throws \MySqlMemory\Error\SqlError When the literal is not valid
     */
    public function compileLiteral(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof NumberLiteral => $this->literals->number($node),
            $node instanceof SignedLiteral => $this->literals->signed($node),
            $node instanceof StringLiteral => $this->literals->string($node),
            $node instanceof RadixLiteral => $this->literals->radix($node),
            $node instanceof TemporalLiteral => $this->literals->temporal($node),
            $node instanceof BooleanLiteral => $this->literals->boolean($node),
            $node instanceof NullLiteral => $this->literals->null($node),
            $node instanceof OdbcEscape => $this->literals->odbc($node, $scope),
            $node instanceof Parameter => $this->names->parameter($node),
            default => null,
        };
    }

    /**
     * Compiles a grouped expression, a column, select item, column default or variable, or answers null for a node of another form.
     *
     * @throws \MySqlMemory\Error\SqlError When the name does not resolve, or a stored program variable is read outside a program
     */
    public function compileName(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof Grouped => $this->compile($node->operand, $scope),
            $node instanceof ColumnUse => $this->names->column($node, $scope),
            $node instanceof OutputOrdinal => $this->names->ordinal($node, $scope),
            $node instanceof DefaultOfColumn => $this->names->default($node, $scope),
            $node instanceof InsertedColumn => $this->names->inserted($node, $scope),
            $node instanceof UserVariable => $this->names->userVariable($node),
            $node instanceof ProgramVariable => throw ProgramError::UndeclaredVariable->error($node->name->value),
            $node instanceof SystemVariable => $this->names->systemVariable($node),
            $node instanceof VariableAssignment => $this->names->assignment($node, $scope),
            default => null,
        };
    }

    /**
     * Compiles an operator, predicate, CASE, cast or JSON operator, or answers null for a node of another form.
     *
     * @throws \MySqlMemory\Error\SqlError When the operator is refused
     */
    public function compileOperator(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof Arithmetic => $this->operators->arithmetic($node, $scope),
            $node instanceof Unary => $this->operators->unary($node, $scope),
            $node instanceof Comparison => $this->operators->comparison($node, $scope),
            $node instanceof Logical => $this->operators->logical($node, $scope),
            $node instanceof Not => $this->operators->not($node, $scope),
            $node instanceof NullTest => $this->operators->nullTest($node, $scope),
            $node instanceof TruthTest => $this->operators->truthTest($node, $scope),
            $node instanceof Between => $this->operators->between($node, $scope),
            $node instanceof InList => $this->operators->inList($node, $scope),
            $node instanceof Like => $this->operators->like($node, $scope),
            $node instanceof CaseExpression => $this->operators->caseOf($node, $scope),
            $node instanceof Cast => $this->operators->cast($node, $scope),
            $node instanceof AtTimeZone => (new Casts($this))->atTimeZone($node, $scope),
            $node instanceof JsonExtraction => $this->jsons->extraction($node, $scope),
            $node instanceof MemberOf => $this->jsons->member($node, $scope),
            default => null,
        };
    }

    /**
     * Compiles a string form written with keywords or operators, or answers null for a node of another form.
     *
     * @throws \MySqlMemory\Error\SqlError When the string form is refused
     */
    public function compileText(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof Collated => $this->texts->collated($node, $scope),
            $node instanceof BinaryCast => $this->texts->binary($node, $scope),
            $node instanceof CharsetConversion => $this->texts->convert($node, $scope),
            $node instanceof Trim => $this->texts->trim($node, $scope),
            $node instanceof Position => $this->texts->position($node, $scope),
            $node instanceof CharCall => $this->texts->char($node, $scope),
            $node instanceof SoundsLike => $this->texts->soundsLike($node, $scope),
            $node instanceof Regexp => $this->texts->regexp($node, $scope),
            $node instanceof WeightString => $this->texts->weight($node, $scope),
            $node instanceof FullTextSearch => $this->texts->match($node, $scope),
            $node instanceof Concatenation => $this->calls->named('CONCAT', [$node->left, $node->right], $scope, $node),
            default => null,
        };
    }

    /**
     * Compiles a function call or date arithmetic, or answers null for a node of another form.
     *
     * An aggregate reaches here only outside the grouped output, where it is refused with ER_INVALID_GROUP_FUNC_USE.
     *
     * @throws \MySqlMemory\Error\SqlError When the call is refused
     */
    public function compileCall(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof IntervalArithmetic => $this->dates->arithmetic($node, $scope),
            $node instanceof Extract => $this->dates->extract($node, $scope),
            $node instanceof IntervalAddition => $this->dates->addition($node, $scope),
            $node instanceof DateArithmetic => $this->dates->call($node, $scope),
            $node instanceof FunctionCall => $this->calls->function($node, $scope),
            $node instanceof KeywordCall => $this->calls->keyword($node, $scope),
            $node instanceof ClockCall => $this->calls->clock($node, $scope),
            $node instanceof Aggregate, $node instanceof GroupConcat => throw QueryError::InvalidGroupFunctionUse->error(),
            default => null,
        };
    }

    /**
     * Compiles a subquery used as a value or in a predicate, or answers null for a node of another form.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery is refused
     */
    public function compileSubquery(Scalar $node, Scope $scope): ?Evaluable
    {
        return match (true) {
            $node instanceof ScalarSubquery => $this->subqueries->scalar($node, $scope),
            $node instanceof Exists => $this->subqueries->exists($node, $scope),
            $node instanceof InQuery => $this->subqueries->in($node, $scope),
            $node instanceof QuantifiedComparison => $this->subqueries->quantifiedComparison($node, $scope),
            default => null,
        };
    }
}
