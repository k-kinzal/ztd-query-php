<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use LogicException;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Remembers parameter types between prepared executions to identify automatic repreparation.
 *
 * MySQL 8.0.22 and later derive a type at preparation. NULL and string arguments do not
 * trigger repreparation; CAST operands retain their target type. Arithmetic and comparison
 * operands inherit the other operand's resolved type. Unresolved arithmetic receives
 * an enclosing numeric context, or DOUBLE when no context supplies a type; DIV defaults
 * to BIGINT. NULL siblings supply VARCHAR and decimal siblings supply DECIMAL(65,30).
 * Repreparation replaces the derived domains; other executions retain those domains and convert
 * incoming values when they are evaluated. Compatible executions reuse the prepared expression information.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html.
 *
 * @visibility MySqlMemory
 */
final class ParameterBindings
{
    /**
     * @param array<int, array{Domain, bool}> $types Derived domain and whether CAST fixes it, by marker position
     */
    public function __construct(public array $types)
    {
    }

    /**
     * Captures initial marker types from the analyzed expression contexts.
     *
     * @throws LogicException When the statement has no preparation implementation
     */
    public static function capture(\SqlSemantics\Statement\AnalyzedStatement $operation): self
    {

        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\RenameUser || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles) {
            return new self([]);
        }
        if (!$operation instanceof MySqlStatement) {
            throw new LogicException('No parameter capture for ' . $operation::class);
        }
        if (\MySqlMemory\Session\Problem\Stages::definitionCondition($operation) && !($operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && $operation->query !== null)) {
            return (new GenerationParameters())->capture($operation);
        }
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update && $operation->hasExecutionCondition()) {
            $markers = (new \MySqlMemory\Evaluation\Compile\Walker())->find($operation, Parameter::class);
            return new self(array_map(static fn ($marker): array => [Domain::string(0, Collation::binary()), false], $markers));
        }
        return new self(array_map(static fn (array $entry): array => [Domain::of($entry[0], true), $entry[1]], \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference::infer($operation)));
    }

    /**
     * Reads the input slot SQL Semantics resolved for a marker occurrence.
     */
    public static function position(MySqlStatement $operation, Parameter $parameter): int
    {
        $binding = $parameter->scalarInformation()->resolution;
        \SqlSemantics\Diagnostic\Check::invariant($binding instanceof \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding, 'A parameter occurrence has a resolved input slot.');

        return $binding->position;
    }

    /**
     * Updates marker types when a bound argument requires the statement to be prepared again.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, Domain}> $parameters
     */
    public function changed(array $parameters): bool
    {
        $changed = false;
        foreach ($parameters as $position => [$value, $domain]) {
            $derived = $this->types[$position] ?? [Domain::string(16383, Collation::binary()), false];
            $changed = $changed || ($value !== null && !$derived[1] && !self::accepts($derived[0]->kind, $derived[0]->unsigned, $domain));
        }
        if ($changed) {
            foreach ($parameters as $position => [$value, $domain]) {
                if ($value !== null && !($this->types[$position][1] ?? false)) {
                    $this->types[$position] = [$domain, false];
                }
            }
        }

        return $changed;
    }

    /**
     * Answers the parameter domains to resolve the next execution with.
     *
     * CAST operands keep their inferred domains when another marker forces repreparation.
     *
     * @return array<int, Domain>
     */
    public function domains(): array
    {
        $domains = [];
        foreach ($this->types as $position => [$domain]) {
            $domains[$position] = $domain;
        }

        return $domains;
    }

    /**
     * Applies the documented type combinations that do not require repreparation.
     */
    public static function accepts(Kind $derived, bool $unsigned, Domain $actual): bool
    {
        $temporal = [Kind::Date, Kind::DateTime, Kind::Time];
        $numeric = [Kind::Integer, Kind::Decimal, Kind::Double];

        return match (true) {
            $actual->kind === Kind::String, $actual->kind === Kind::Null => true,
            $derived === Kind::Integer && $actual->kind === Kind::Integer => $unsigned === $actual->unsigned,
            $derived === Kind::Decimal => in_array($actual->kind, [Kind::Integer, Kind::Decimal], true),
            $derived === Kind::Double => in_array($actual->kind, $numeric, true),
            in_array($derived, $temporal, true) => in_array($actual->kind, $numeric, true) || ($derived === Kind::DateTime ? in_array($actual->kind, $temporal, true) : $actual->kind === $derived),
            default => $derived === $actual->kind,
        };
    }
}
