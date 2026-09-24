<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\StoredSettings;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Classifies the attributes shared by CREATE and ALTER of PostgreSQL routines.
 * @visibility SqlSemantics
 */
final class RoutineOptions
{
    /**
     * Reads every common attribute below the node in request order.
     * @return list<Option\RoutineOption|RoutineSecurity>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Origin $origin, Node $source, QueryContext $context): array
    {
        return array_map(static fn (Node $item): Option\RoutineOption|RoutineSecurity => self::option($origin, $item, $context), Tree::outer($source, ['common_func_opt_item']));
    }

    /**
     * Classifies one attribute; EXTERNAL is noise and RETURNS NULL ON NULL INPUT is STRICT.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(Origin $origin, Node $item, QueryContext $context): Option\RoutineOption|RoutineSecurity
    {
        $words = ObjectAddresses::words($item);
        $clause = Tree::child($item, ['FunctionSetResetClause']);
        if ($clause !== null) {
            return self::setting($origin, $clause, $context);
        }
        try {
            return match ($words[0] === 'EXTERNAL' ? $words[1] : $words[0]) {
                'CALLED' => Option\NullInputBehavior::Called,
                'RETURNS', 'STRICT' => Option\NullInputBehavior::Strict,
                'IMMUTABLE', 'STABLE', 'VOLATILE' => Option\Volatility::from($words[0]),
                'SECURITY' => RoutineSecurity::from($words[count($words) - 1]),
                'LEAKPROOF' => Option\LeakproofBehavior::Leakproof,
                'NOT' => Option\LeakproofBehavior::NotLeakproof,
                'COST' => new Option\ExecutionCost(self::estimate($item)),
                'ROWS' => new Option\ResultRows(self::estimate($item)),
                'SUPPORT' => new Option\SupportFunction(ObjectAddresses::name(Tree::child($item, ['any_name']) ?? throw new UnclassifiedSql('SUPPORT requires a function.'), $context, 3)),
                'PARALLEL' => self::parallel($item, $context),
                default => throw new UnclassifiedSql('Unclassified routine attribute: ' . Tree::text($item)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineAttribute, $item, $error);
        }
    }

    /**
     * PARALLEL takes the lower-case words safe, restricted, or unsafe.
     * @throws InvalidSql
     */
    public static function parallel(Node $item, QueryContext $context): Option\ParallelSafety
    {
        $value = $context->tables->identifiers->name($item->tokens()[1] ?? throw new InvalidSql(InputViolation::RoutineAttribute, $item));
        return $value === strtolower($value) ? Option\ParallelSafety::tryFrom(strtoupper($value)) ?? throw new InvalidSql(InputViolation::RoutineAttribute, $item) : throw new InvalidSql(InputViolation::RoutineAttribute, $item);
    }

    /**
     * SET stores a parameter value for the routine's execution; RESET removes one or all stored values.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function setting(Origin $origin, Node $clause, QueryContext $context): Option\RoutineSetting|Option\RoutineReset
    {
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $reset = Tree::child($clause, ['VariableResetStmt']);
        return $reset === null ? new Option\RoutineSetting(StoredSettings::assignment($clause, $scope)) : new Option\RoutineReset(StoredSettings::reset($origin, $reset, $scope));
    }

    /**
     * A COST or ROWS estimate is a positive number; a plus sign and digit separators are dropped.
     * @throws InvalidSql
     */
    public static function estimate(Node $item): Literal
    {
        $number = Tree::child($item, ['NumericOnly']) ?? $item;
        $tokens = $number->tokens();
        $digits = $tokens[count($tokens) - 1];
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($digits);
        if (!$literal instanceof Literal || $tokens[0]->text === '-') {
            throw new InvalidSql(InputViolation::RoutineAttribute, $number);
        }
        try {
            return new Literal($literal->facts, $digits, $literal->literalKind, str_replace('_', '', $digits->text));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineAttribute, $number, $error);
        }
    }
}
