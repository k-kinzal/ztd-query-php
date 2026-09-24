<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TriggerRow;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The names a stored program body declares for ordinary expressions: local variables and a trigger's OLD and NEW rows.
 * A local variable takes precedence over a column of the same name, as in MySQL.
 * @visibility SqlSemantics
 */
final class ProgramNamespace
{
    /**
     * @param array<string, LocalVariable> $variables Visible variables by case-folded name
     * @param array<string, TriggerRow> $rows Row images the trigger event supplies, by case-folded version name
     */
    public function __construct(public readonly array $variables = [], public readonly bool $trigger = false, public readonly array $rows = [])
    {
    }

    /**
     * Returns a namespace in which the given variables shadow earlier ones of the same name.
     * @param list<LocalVariable> $variables
     */
    public function declare(array $variables): self
    {
        $visible = $this->variables;
        foreach ($variables as $variable) {
            $visible[strtolower($variable->name)] = $variable;
        }
        return new self($visible, $this->trigger, $this->rows);
    }

    /**
     * Looks up a visible local variable.
     */
    public function variable(string $name): ?LocalVariable
    {
        return $this->variables[strtolower($name)] ?? null;
    }

    /**
     * Resolves a program name through the namespace attached to the scope's tables; null outside stored programs.
     * @param list<string> $parts
     * @throws InvalidSql
     */
    public static function lookup(Scope $scope, array $parts, Node|Token $source): ?Expression
    {
        return $scope->queries?->tables->program?->resolve($scope, $parts, $source);
    }

    /**
     * Resolves an unqualified local variable or a trigger row column; returns null for every other name.
     * @param list<string> $parts
     * @throws InvalidSql
     */
    public function resolve(Scope $scope, array $parts, Node|Token $source): ?Expression
    {
        if (count($parts) === 1 && ($variable = $this->variable($parts[0])) !== null) {
            return new LocalVariableReference(new ExpressionFacts($variable->domain->type, Nullability::MaybeNull), $source, $variable);
        }
        if (count($parts) !== 2 || !$this->trigger || !in_array(strtolower($parts[0]), ['new', 'old'], true)) {
            return null;
        }
        $row = $this->rows[strtolower($parts[0])] ?? throw new InvalidSql(InputViolation::ProgramObject, $source);
        foreach ($row->declaration->columns as $column) {
            if ($scope->identifiers->equal($column->name, $parts[1])) {
                return new TriggerColumn(new ExpressionFacts($column->type, $column->nullability), $source, new ColumnBinding($row->id, $row->declaration, $column), $row->version);
            }
        }
        $scope->diagnostics()->report('unknown-column', 'Cannot resolve trigger column: ' . implode('.', $parts), $source);
        return new UnresolvedColumnReference(new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), Nullability::Unknown), $source, $parts);
    }
}
