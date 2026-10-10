<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Pattern;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles [NOT] LIKE and its ESCAPE expression.
 *
 * An ESCAPE expression that varies by row is refused (ER_WRONG_ARGUMENTS). One known when the
 * statement is resolved is evaluated then, and refused when it is more than one character; one
 * known only when the statement runs, such as USER() or a user variable, is checked when the
 * first row is matched, so a LIKE never evaluated never refuses it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-comparison-functions.html#operator_like.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Patterns
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles [NOT] LIKE, comparing in the collation the operand and the pattern aggregate to.
     *
     * @throws \MySqlMemory\Error\SqlError When the escape varies by row, or is known to be more than one character
     */
    public function like(Like $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $constancy = $node->escape === null ? Constancy::Resolved : $this->compiler->constancy($node->escape);
        if ($constancy === Constancy::Row) {
            throw StatementError::WrongArguments->error('ESCAPE');
        }
        $escape = match (true) {
            $node->escape === null => null,
            $constancy === Constancy::Statement => $this->compiler->compile($node->escape, $scope),
            default => $this->escape($node->escape, $scope),
        };
        [$collation] = Collations::aggregate([$operand->domain(), $pattern->domain()], 'like', $this->compiler->settings->connectionCollation, true, $this->compiler->settings->release());
        $operand = \MySqlMemory\Evaluation\Operator\Transcoded::of($operand, $collation, $this->compiler->constancy($node->operand) === Constancy::Resolved, $this->compiler->connection->context);
        $pattern = \MySqlMemory\Evaluation\Operator\Transcoded::of($pattern, $collation, $this->compiler->constancy($node->pattern) === Constancy::Resolved, $this->compiler->connection->context);

        return new Pattern($operand, $pattern, $escape, $collation, $node->negated, $this->compiler->domain($node), $constancy === Constancy::Statement);
    }

    /**
     * Compiles and evaluates an ESCAPE expression known when the statement is resolved into its value.
     *
     * @throws \MySqlMemory\Error\SqlError When the escape is more than one character
     */
    public function escape(Scalar $node, Scope $scope): Evaluable
    {
        $compiled = $this->compiler->compile($node, $scope);
        $value = $compiled->evaluate(new Frame($this->compiler->connection->context));
        $text = Convert::toText($value, $compiled->domain());
        if ($text !== null && (new Strings())->count($text, $compiled->domain()) > 1) {
            throw StatementError::WrongArguments->error('ESCAPE');
        }

        return new Constant($compiled->domain(), $value);
    }
}
