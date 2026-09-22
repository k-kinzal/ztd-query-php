<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Configuration\Pragma;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Configuration;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies SQLite pragma reads and scalar arguments.
 *
 * @visibility SqlSemantics
 */
final class PragmaBinder
{
    /**
     * Binds the scalar grammar without accepting arbitrary expressions or argument lists.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, Scope $scope): Configuration\ReadPragmaStatement|Configuration\AssignPragmaStatement
    {
        $nameNode = Tree::child($source, ['nm']);
        if ($nameNode === null) {
            throw new UnclassifiedSql('A pragma requires its name.');
        }
        $parts = $scope->identifiers->parts($nameNode);
        $suffix = Tree::child($source, ['dbnm']);
        if ($suffix !== null) {
            array_push($parts, ...$scope->identifiers->parts($suffix));
        }
        $name = new QualifiedName($parts);
        $value = Tree::child($source, ['nmnum', 'minus_num']);
        if ($value === null) {
            return new Configuration\ReadPragmaStatement($origin, $name);
        }
        $tokens = $value->tokens();
        $sign = Pragma\Sign::tryFrom($tokens[0]->text) ?? Pragma\Sign::Unsigned;
        if ($sign !== Pragma\Sign::Unsigned) {
            array_shift($tokens);
        }
        if (count($tokens) !== 1) {
            throw new UnclassifiedSql('A pragma argument requires one scalar value.');
        }
        $literal = (new LiteralBinder($scope->identifiers->dialect))->bind($tokens[0]);
        $argument = match (true) {
            $literal instanceof Literal && $literal->literalKind === LiteralKind::Number => new Pragma\NumericArgument($literal, $sign),
            $literal instanceof Literal && $literal->literalKind === LiteralKind::Text => new Pragma\TextArgument($literal),
            default => new Pragma\IdentifierArgument($scope->identifiers->name($tokens[0])),
        };
        return new Configuration\AssignPragmaStatement($origin, $name, $argument);
    }
}
