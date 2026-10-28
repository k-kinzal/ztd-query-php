<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Prepared;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding;
use SqlSemantics\Statement\Type\Known;

/**
 * The initial marker domains of a prepared statement, derived from semantic facts.
 *
 * Arithmetic and comparison markers inherit their sibling's domain, widening
 * integers to BIGINT and decimals to DECIMAL(65,30). An unresolved arithmetic
 * subtree receives its enclosing numeric context before falling back to DOUBLE;
 * integer division defaults to BIGINT. CASE and control-function result branches supply the
 * other branch types. A NULL sibling supplies binary VARCHAR; binding a numeric
 * value can then trigger automatic repreparation. CAST records that
 * its argument is fixed against automatic repreparation. Other positions retain
 * their supplied preparation domain. Consumers retain these domains between
 * executions and supply them as parameter settings when resolving again.
 *
 * This rule inspects neither source text nor parser nodes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html.
 *
 * @visibility public
 * @example Inferring an arithmetic marker without a typed sibling
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT ? / ?');
 *     \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference::infer($operation)[0][0]->kind // => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Double
 */
final class ParameterInference
{
    /**
     * @return array<int, array{Domain, bool}> Initial type and CAST fixation by input position
     */
    public static function infer(Statement $operation): array
    {
        $nodes = (new ParameterContexts())->expressions($operation);
        $types = self::initial($operation, $nodes);
        foreach ($nodes as $expression) {
            if ($expression instanceof Unary && $expression->operator === UnaryOperator::Invert && $expression->operand instanceof Parameter) {
                $binding = $expression->operand->scalarInformation()->resolution;
                Check::invariant($binding instanceof ParameterBinding, 'A bitwise marker has a resolved input slot.');
                $types[$binding->position] = [Domain::string(65535, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible), false];
            }
            if ($expression instanceof Cast && $expression->operand instanceof Parameter) {
                $binding = $expression->operand->scalarInformation()->resolution;
                Check::invariant($binding instanceof ParameterBinding, 'A CAST marker has a resolved input slot.');
                Check::invariant(isset($types[$binding->position]), 'The CAST marker has an initial domain.');
                $types[$binding->position] = [$types[$binding->position][0], true];
            }
        }
        $types = array_replace($types, self::contexts($operation, $nodes) + self::defaults($nodes));
        ksort($types);

        return $types;
    }

    /**
     * Applies sibling and result-branch contexts before numeric fallback types.
     *
     * @param list<\SqlSemantics\Statement\Scalar> $nodes Expressions in this parameter scope
     * @return array<int, array{Domain, bool}> Explicitly inferred marker domains
     */
    public static function contexts(Statement $operation, array $nodes): array
    {
        $numeric = [];
        foreach ($nodes as $expression) {
            if ($expression instanceof Arithmetic || $expression instanceof Comparison) {
                foreach ([[$expression->left, $expression->right], [$expression->right, $expression->left]] as [$operand, $other]) {
                    if ($operand instanceof \SqlSemantics\Statement\Scalar && $other instanceof \SqlSemantics\Statement\Scalar && self::pending($operand) && !self::pending($other)) {
                        $domain = self::resolved($other);
                        if ($domain !== null) {
                            $numeric += self::propagate($operand, self::operand($domain, $expression instanceof Arithmetic));
                        }
                    }
                }
            }
            foreach ((new BranchParameters())->infer($expression, $operation) as $position => $domain) {
                $numeric[$position] = [$domain, false];
            }
        }
        return $numeric;
    }

    /**
     * Supplies DOUBLE for unresolved arithmetic and BIGINT for integer division.
     *
     * @param list<\SqlSemantics\Statement\Scalar> $nodes Expressions in this parameter scope
     * @return array<int, array{Domain, bool}> Numeric fallback marker domains
     */
    public static function defaults(array $nodes): array
    {
        $numeric = [];
        foreach ($nodes as $expression) {
            if ($expression instanceof Arithmetic && !$expression->operator->bitwise()) {
                $domain = $expression->operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::IntegerDivide ? Domain::integer() : Domain::double();
                if (self::pending($expression->left) && self::pending($expression->right)) {
                    $numeric += self::propagate($expression->left, $domain) + self::propagate($expression->right, $domain);
                }
            }
            if ($expression instanceof Unary && $expression->operator === UnaryOperator::Minus && self::pending($expression)) {
                $numeric += self::propagate($expression, Domain::double());
            }
        }
        return $numeric;
    }

    /**
     * Resolves a sibling's numeric kind without mistaking unbound placeholders for strings.
     */
    public static function resolved(\SqlSemantics\Statement\Scalar $expression): ?Domain
    {
        if (self::pending($expression)) {
            return null;
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus)) {
            return self::resolved($expression->operand);
        }
        if ($expression instanceof Arithmetic && !$expression->operator->bitwise()) {
            $left = self::resolved($expression->left);
            $right = self::resolved($expression->right);
            if ($left === null && $right === null) {
                return Domain::integer();
            }
            $numbers = new \SqlSemantics\Platform\MySql\Rules\Typing\Numbers();
            $left ??= self::operand($right, true);
            $right ??= self::operand($left, true);
            return $numbers->binary($expression->operator, $numbers->numeric($expression->left, $left), $numbers->numeric($expression->right, $right));
        }
        if ($expression instanceof Unary && $expression->operator === UnaryOperator::Minus) {
            $operand = self::resolved($expression->operand);
            return $operand === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Typing\Numbers())->negated($operand);
        }
        return (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($expression->scalarInformation()->type);
    }

    /**
     * Reports expressions whose numeric type must come from an enclosing context.
     */
    public static function pending(\SqlSemantics\Statement\Scalar $expression): bool
    {
        $marker = self::marker($expression);
        if ($marker !== null) {
            return $marker->unbound;
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($expression instanceof Unary && in_array($expression->operator, [UnaryOperator::Plus, UnaryOperator::Minus], true))) {
            return self::pending($expression->operand);
        }
        return $expression instanceof Arithmetic && !$expression->operator->bitwise() && $expression->operator !== \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::IntegerDivide && self::pending($expression->left) && self::pending($expression->right);
    }

    /**
     * Propagates an enclosing numeric type through unresolved arithmetic operands.
     *
     * @return array<int, array{Domain, bool}> Marker types owned by this unresolved subtree
     */
    public static function propagate(\SqlSemantics\Statement\Scalar $expression, Domain $domain): array
    {
        $marker = self::marker($expression);
        if ($marker !== null) {
            return [$marker->position => [$domain, false]];
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || $expression instanceof Unary) {
            return self::propagate($expression->operand, $domain);
        }
        Check::invariant($expression instanceof Arithmetic, 'An unresolved numeric expression has arithmetic operands.');
        return self::propagate($expression->left, $domain) + self::propagate($expression->right, $domain);
    }

    /**
     * Reads the input slot through parentheses and unary plus, which preserve the marker.
     */
    public static function marker(\SqlSemantics\Statement\Scalar $expression): ?ParameterBinding
    {
        while ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus)) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof Parameter) {
            return null;
        }
        $binding = $expression->scalarInformation()->resolution;
        Check::invariant($binding instanceof ParameterBinding, 'A parameter occurrence has a resolved input slot.');
        return $binding;
    }

    /**
     * Resolves the initial domain of a marker from its direct sibling.
     */
    public static function operand(Domain $other, bool $arithmetic): Domain
    {
        return match (true) {
            $other->kind === Kind::Integer => Domain::integer(unsigned: $other->unsigned),
            $other->kind === Kind::Decimal => Domain::decimal(65, 30),
            $arithmetic && $other->kind === Kind::Null => Domain::string(65535, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible),
            default => $other,
        };
    }
    /**
     * Reads the supplied preparation domain of each parameter occurrence.
     *
     * @param list<\SqlSemantics\Statement\Scalar> $nodes Expressions in this parameter scope
     * @return array<int, array{Domain, bool}> Initial domains by written position
     */
    public static function initial(Statement $operation, array $nodes): array
    {
        $types = [];
        foreach ($nodes as $marker) {
            if ($marker instanceof Parameter) {
                $fact = $marker->scalarInformation();
                Check::invariant($fact->resolution instanceof ParameterBinding, 'A parameter occurrence has a resolved input slot.');
                $domain = $fact->type instanceof Known && $fact->type->descriptor instanceof Domain ? $fact->type->descriptor : Domain::string(16383, ($operation->statementInformation()->connection ?? Settings::defaults($operation->profile()->grammar)->connection), coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
                $types[$fact->resolution->position] = [$domain, false];
            }
        }
        return $types;
    }

}
