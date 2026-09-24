<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Column\IdentityMode;

/**
 * Binds identity column additions and the sequence options of identity changes.
 * @visibility SqlSemantics
 */
final class IdentityActions
{
    /**
     * ADD GENERATED { ALWAYS | BY DEFAULT } AS IDENTITY [( sequence options )].
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(string $column, Node $command, Scope $scope, QueryContext $context): Identity\AddColumnIdentity
    {
        $when = Tree::child($command, ['generated_when']) ?? throw new UnclassifiedSql('ADD GENERATED requires ALWAYS or BY DEFAULT.');
        $options = array_map(static fn (Node $option) => self::option($option, $scope, $context), Tree::outer($command, ['SeqOptElem']));
        return new Identity\AddColumnIdentity($column, self::mode($when), $options);
    }

    /**
     * Ordered SET GENERATED, SET option, and RESTART changes of an existing identity column.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(string $column, Node $list, Scope $scope, QueryContext $context): Identity\SetColumnIdentity
    {
        $changes = [];
        foreach (Tree::outer($list, ['alter_identity_column_option']) as $option) {
            $when = Tree::child($option, ['generated_when']);
            $element = Tree::child($option, ['SeqOptElem']);
            if ($when !== null) {
                $changes[] = self::mode($when);
            } elseif ($element !== null) {
                $change = self::option($element, $scope, $context);
                if ($change instanceof Identity\SequenceStorage || $change instanceof Identity\SetSequenceOwner || $change instanceof Identity\RestartIdentity) {
                    throw new InvalidSql(InputViolation::IdentityOption, $option);
                }
                $changes[] = $change;
            } else {
                $changes[] = new Identity\RestartIdentity(self::value(Tree::child($option, ['NumericOnly']), $scope));
            }
        }
        return new Identity\SetColumnIdentity($column, Collections::nonEmpty($changes));
    }

    /**
     * Maps ALWAYS and BY DEFAULT to the identity mode.
     */
    public static function mode(Node $when): IdentityMode
    {
        return strtoupper(Tree::text($when)) === 'ALWAYS' ? IdentityMode::Always : IdentityMode::ByDefault;
    }

    /**
     * Classifies one sequence option into a value, flag, name, storage, owner, or restart.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(Node $option, Scope $scope, QueryContext $context): Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SetSequenceName|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity
    {
        $words = ObjectAddresses::words($option);
        $number = Tree::child($option, ['NumericOnly']);
        $name = Tree::child($option, ['any_name']);
        return match ($words[0]) {
            'AS' => new Identity\SequenceStorage((new TypeReader(Dialect::PostgreSql))->read(Tree::child($option, ['SimpleTypename']) ?? throw new UnclassifiedSql('AS requires a type.'))),
            'CACHE' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Cache, self::integer($number, $scope)),
            'INCREMENT' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Increment, self::integer($number, $scope)),
            'MAXVALUE' => new Identity\SequenceValueChange(Identity\SequenceAttribute::MaxValue, self::integer($number, $scope)),
            'MINVALUE' => new Identity\SequenceValueChange(Identity\SequenceAttribute::MinValue, self::integer($number, $scope)),
            'START' => new Identity\SequenceValueChange(Identity\SequenceAttribute::Start, self::integer($number, $scope)),
            'CYCLE' => Identity\SequenceFlag::Cycle,
            'LOGGED' => Identity\SequenceFlag::Logged,
            'UNLOGGED' => Identity\SequenceFlag::Unlogged,
            'NO' => Identity\SequenceFlag::from('NO ' . ($words[1] ?? '')),
            'OWNED' => new Identity\SetSequenceOwner(self::owner($name ?? throw new UnclassifiedSql('OWNED BY requires a column.'), $context)),
            'SEQUENCE' => new Identity\SetSequenceName(ObjectAddresses::name($name ?? throw new UnclassifiedSql('SEQUENCE NAME requires a name.'), $context, 3)),
            'RESTART' => new Identity\RestartIdentity(self::value($number, $scope)),
            default => throw new UnclassifiedSql('Unclassified sequence option: ' . Tree::text($option)),
        };
    }

    /**
     * OWNED BY NONE detaches the sequence; anything else names a table column.
     * @throws InvalidSql
     */
    public static function owner(Node $name, QueryContext $context): ?\SqlSemantics\Model\Relation\QualifiedName
    {
        $parts = $context->tables->identifiers->parts($name);
        return count($parts) === 1 && $parts[0] === 'none' ? null : ObjectAddresses::name($name, $context, 4);
    }

    /**
     * Requires the numeric operand.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function integer(?Node $number, Scope $scope): Literal
    {
        return self::value($number ?? throw new UnclassifiedSql('The sequence option requires its value.'), $scope) ?? throw new UnclassifiedSql('The sequence option requires its value.');
    }

    /**
     * Sequence values are integer literals; signs fold into the literal spelling.
     * @throws InvalidSql
     */
    public static function value(?Node $number, Scope $scope): ?Literal
    {
        if ($number === null) {
            return null;
        }
        $tokens = $number->tokens();
        $value = count($tokens) === 2 && in_array($tokens[0]->text, ['-', '+'], true) ? self::signed($tokens[0]->text, $tokens[1]) : (new ExpressionBinder())->bind($number, $scope);
        if (!$value instanceof Literal) {
            throw new InvalidSql(InputViolation::SequenceAttribute, $number);
        }
        try {
            Identity\IdentityInvariant::integer($value);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceAttribute, $number, $error);
        }
        return $value;
    }

    /**
     * Folds a sign written before an unsigned integer into one literal spelling.
     * @throws InvalidSql
     */
    public static function signed(string $sign, \SqlParser\Lexer\Token $digits): ?\SqlSemantics\Model\Expression
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::PostgreSql))->bind($digits);
        if (!$literal instanceof Literal) {
            return $literal;
        }
        try {
            return new Literal($literal->facts, $digits, $literal->literalKind, $sign . $literal->text);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceAttribute, $digits, $error);
        }
    }
}
