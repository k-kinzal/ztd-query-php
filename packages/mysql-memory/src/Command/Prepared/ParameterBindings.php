<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as ResolvedDomain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;

/**
 * Remembers parameter types between prepared executions to identify automatic repreparation.
 *
 * MySQL 8.0.22 and later derive a type at preparation. NULL and string arguments do not
 * trigger repreparation; CAST operands retain their target type. Arithmetic and comparison
 * operands inherit the other operand's resolved type. Other contexts currently start as VARCHAR.
 * Repreparation replaces the derived domains; other executions retain those domains and convert
 * incoming values when they are evaluated. Execution resolves a fresh operation with these types.
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
     */
    public static function capture(Operation $operation): self
    {
        $types = new self([]);
        $nodes = (new Walker())->find($operation->statement, Scalar::class);
        foreach ($nodes as $node) {
            if ($node instanceof Parameter && $node->position !== null) {
                $type = $operation->facts->scalar($node)->type;
                $domain = $type instanceof Known && $type->descriptor instanceof ResolvedDomain ? Domain::of($type->descriptor, true) : Domain::string(16383, Collation::binary());
                $types->types[$node->position] = [$domain, false];
            }
        }
        foreach ($nodes as $node) {
            if ($node instanceof Arithmetic || $node instanceof Comparison) {
                $types->operand($node->left, $node->right, $operation);
                $types->operand($node->right, $node->left, $operation);
            }
            if ($node instanceof Cast && $node->operand instanceof Parameter && $node->operand->position !== null) {
                $types->types[$node->operand->position][1] = true;
            }
        }

        return $types;
    }

    /**
     * Derives a direct marker operand from the resolved type of its sibling.
     */
    public function operand(Scalar $operand, Scalar $other, Operation $operation): void
    {
        if (!$operand instanceof Parameter || $operand->position === null || $other instanceof Parameter || !$operation->facts->covers($other)) {
            return;
        }
        $type = $operation->facts->scalar($other)->type;
        if ($type instanceof Known && $type->descriptor instanceof ResolvedDomain) {
            $domain = Domain::of($type->descriptor, true);
            $this->types[$operand->position] = [$domain->kind === Kind::Integer ? Domain::integer(unsigned: $domain->unsigned) : $domain, false];
        }
    }

    /**
     * Updates marker types when a bound argument requires the statement to be prepared again.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters
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
     * CAST performs its own conversion, so its argument keeps the actual domain.
     *
     * @return array<int, Domain>
     */
    public function domains(): array
    {
        $domains = [];
        foreach ($this->types as $position => [$domain, $cast]) {
            if (!$cast) {
                $domains[$position] = $domain;
            }
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
