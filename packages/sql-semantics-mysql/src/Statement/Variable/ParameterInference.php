<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;

/**
 * The initial marker domains of a prepared statement, derived from semantic facts.
 *
 * Arithmetic and comparison markers inherit their sibling's domain, widening
 * integers to BIGINT. CASE and control-function result branches supply the
 * other branch types. Arithmetic against NULL needs DOUBLE. CAST records that
 * its argument is fixed against automatic repreparation. Other positions retain
 * their supplied preparation domain. Consumers retain these domains between
 * executions and supply them as parameter settings when resolving again.
 *
 * This rule inspects neither source text nor parser nodes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html.
 *
 * @visibility public
 * @example Inferring an arithmetic marker from a NULL sibling
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT NULL DIV ?');
 *     \SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference::infer($operation)[0][0]->kind // => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Double
 */
final class ParameterInference
{
    /**
     * @return array<int, array{Domain, bool}> Initial type and CAST fixation by input position
     */
    public static function infer(Operation $operation): array
    {
        $types = [];
        $nodes = (new \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterContexts())->expressions($operation->statement);
        foreach ($nodes as $marker) {
            if ($marker instanceof Parameter) {
                $fact = $operation->facts->scalar($marker);
                Check::invariant($fact->resolution instanceof ParameterBinding, 'A parameter occurrence has a resolved input slot.');
                $domain = $fact->type instanceof Known && $fact->type->descriptor instanceof Domain ? $fact->type->descriptor : Domain::string(16383, Settings::of($operation->context)->connection, coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
                $types[$fact->resolution->position] = [$domain, false];
            }
        }
        foreach ($nodes as $expression) {
            foreach ((new \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\BranchParameters())->infer($expression, $operation) as $position => $domain) {
                $types[$position] = [$domain, false];
            }
            if ($expression instanceof Arithmetic || $expression instanceof Comparison) {
                foreach ([[$expression->left, $expression->right], [$expression->right, $expression->left]] as [$operand, $other]) {
                    if (!$operand instanceof Parameter || $other instanceof Parameter || !$operation->facts->covers($other)) {
                        continue;
                    }
                    $fact = $operation->facts->scalar($other);
                    $binding = $operation->facts->scalar($operand)->resolution;
                    $sibling = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($fact->type);
                    if ($binding instanceof ParameterBinding && $sibling !== null) {
                        $types[$binding->position] = [self::operand($sibling, $expression instanceof Arithmetic), false];
                    }
                }
            }
            if ($expression instanceof Unary && $expression->operator === UnaryOperator::Invert && $expression->operand instanceof Parameter) {
                $binding = $operation->facts->scalar($expression->operand)->resolution;
                Check::invariant($binding instanceof ParameterBinding, 'A bitwise marker has a resolved input slot.');
                $types[$binding->position] = [Domain::string(65535, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible), false];
            }
            if ($expression instanceof Cast && $expression->operand instanceof Parameter) {
                $binding = $operation->facts->scalar($expression->operand)->resolution;
                Check::invariant($binding instanceof ParameterBinding, 'A CAST marker has a resolved input slot.');
                Check::invariant(isset($types[$binding->position]), 'The CAST marker has an initial domain.');
                $types[$binding->position] = [$types[$binding->position][0], true];
            }
        }
        ksort($types);

        return $types;
    }

    /**
     * Resolves the initial domain of a marker from its direct sibling.
     */
    public static function operand(Domain $other, bool $arithmetic): Domain
    {
        return match (true) {
            $other->kind === Kind::Integer => Domain::integer(unsigned: $other->unsigned),
            $arithmetic && $other->kind === Kind::Null => Domain::double(),
            default => $other,
        };
    }
}
