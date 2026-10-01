<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\BinaryOperator;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;
use SqlSemantics\Semantic\Expression\UnaryOperator;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Scope;

/**
 * Lowers parsed expression boundaries into operations and references.
 * @visibility SqlSemantics
 */
final class ExpressionReader
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node|Token $node, Scope $scope): ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf
    {
        if ($node instanceof Token) {
            return $this->terminal($node, $scope);
        }
        $syntax = $scope->dialect->platform()->syntax();
        if (in_array($node->name, $syntax->nodes('columnReference'), true)) {
            return $this->column($node, $scope);
        }
        $children = Tree::significant($node);
        if (count($children) === 1) {
            return $this->read($children[0], $scope);
        }
        if (count($children) === 3 && Tree::text($children[0]) === '(' && Tree::text($children[2]) === ')') {
            return $this->read($children[1], $scope);
        }
        if (in_array($node->name, $syntax->nodes('qualifiedExpression'), true) && (new Names(new Identifiers($scope->dialect)))->qualified($children, $syntax)) {
            return $this->column($node, $scope);
        }
        if (isset($children[1]) && Tree::text($children[1]) === '(') {
            return $this->call($node, $children, $scope);
        }
        return $this->operation($node, $children, $scope);
    }

    /**
     * Creates a column reference whose facts are derived from this scope.
     */
    public function column(Node|Token $node, Scope $scope): ColumnReference
    {
        $parts = (new Names(new Identifiers($scope->dialect)))->parts($node);
        if (count($parts) > 3) {
            Tree::unsupported($node, 'column qualification');
        }
        $name = $parts[count($parts) - 1];
        $qualifier = count($parts) < 2 ? null : new QualifiedName($parts[count($parts) - 2], count($parts) === 3 ? $parts[0] : null);
        return $scope->column($name, $qualifier);
    }

    /**
     * Distinguishes a literal, a parameter, and a column reference.
     */
    public function terminal(Token $token, Scope $scope): ColumnReference|Literal|Parameter
    {
        $syntax = $scope->dialect->platform()->syntax();
        if (in_array($token->name, $syntax->nodes('parameterToken'), true)) {
            return new Parameter($token->text);
        }
        if (in_array($token->name, $syntax->nodes('identifierToken'), true)) {
            $column = $this->column($token, $scope);
            if (in_array(strtoupper($token->text), ['TRUE', 'FALSE'], true)) {
                if ($column->binding instanceof \SqlSemantics\Semantic\Reference\MissingColumn) {
                    return new Literal($scope->dialect, strtoupper($token->text) === 'TRUE');
                }
                if ($column->binding instanceof \SqlSemantics\Semantic\Reference\CandidateColumn) {
                    Tree::unsupported($token, 'boolean identifier resolution without a catalog');
                }
            }
            return $column;
        }
        $text = strtoupper($token->text);
        if (in_array($text, ['NULL', 'TRUE', 'FALSE'], true)) {
            return new Literal($scope->dialect, match ($text) {
                'NULL' => null, 'TRUE' => true, 'FALSE' => false
            });
        }
        if (ctype_digit($token->text) && strlen(ltrim($token->text, '0')) < 19) {
            return new Literal($scope->dialect, (int) $token->text);
        }
        if (in_array($token->name, $syntax->nodes('stringToken'), true) && str_starts_with($token->text, "'") && !str_contains($token->text, '\\')) {
            return new Literal($scope->dialect, str_replace("''", "'", substr($token->text, 1, -1)));
        }
        Tree::unsupported($token, 'scalar terminal');
    }

    /**
     * @param list<Node|Token> $children
     */
    public function operation(Node $node, array $children, Scope $scope): Binary|Unary
    {
        if (count($children) === 2) {
            $operator = UnaryOperator::tryFrom(strtoupper(Tree::text($children[0])));
            if ($operator !== null) {
                return new Unary($scope, $operator, $this->read($children[1], $scope));
            }
        }
        if (count($children) >= 2) {
            $tail = strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1))));
            $operator = match ($tail) {
                'IS NULL', 'ISNULL' => UnaryOperator::IsNull, 'IS NOT NULL', 'NOTNULL' => UnaryOperator::IsNotNull, default => null
            };
            if ($operator !== null) {
                return new Unary($scope, $operator, $this->read($children[0], $scope));
            }
        }
        if (count($children) === 3) {
            $text = strtoupper(Tree::text($children[1]));
            $operator = BinaryOperator::tryFrom($text === '!=' ? '<>' : $text);
            if ($operator !== null) {
                return new Binary($scope, $operator, $this->read($children[0], $scope), $this->read($children[2], $scope));
            }
        }
        Tree::unsupported($node, 'scalar operation');
    }

    /**
     * @param list<Node|Token> $children
     */
    public function call(Node $node, array $children, Scope $scope): Coalesce|NullIf
    {
        $name = strtoupper(Tree::text($children[0]));
        if (!in_array($name, ['COALESCE', 'NULLIF'], true)) {
            Tree::unsupported($node, 'function semantics');
        }
        $operands = [];
        foreach (array_slice($children, 2, -1) as $child) {
            if ($child instanceof Node) {
                foreach (Tree::outer($child, $scope->dialect->platform()->syntax()->nodes('expression')) as $argument) {
                    $operands[] = $this->read($argument, $scope);
                }
            }
        }
        if ($operands === [] || ($name === 'NULLIF' && count($operands) !== 2)) {
            Tree::unsupported($node, 'function arity');
        }
        return $name === 'NULLIF' ? new NullIf($scope, $operands[0], $operands[1]) : new Coalesce($scope, $operands[0], ...array_slice($operands, 1));
    }
}
