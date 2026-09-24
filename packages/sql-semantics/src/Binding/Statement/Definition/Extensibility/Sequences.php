<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\Sequence as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE SEQUENCE and ALTER SEQUENCE with their typed options.
 * @visibility SqlSemantics
 */
final class Sequences
{
    /**
     * The leading keyword of each numeric option and the attribute it sets.
     */
    public const ATTRIBUTES = ['CACHE' => 'CACHE', 'INCREMENT' => 'INCREMENT BY', 'MAXVALUE' => 'MAXVALUE', 'MINVALUE' => 'MINVALUE', 'START' => 'START WITH'];

    /**
     * CREATE [TEMPORARY | UNLOGGED] SEQUENCE [IF NOT EXISTS] name [options].
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): Statement\CreateSequenceStatement
    {
        $temp = Tree::child($source, ['OptTemp']);
        $persistence = $temp === null ? Statement\SequencePersistence::Permanent : (strtoupper(Tree::text($temp)) === 'UNLOGGED' ? Statement\SequencePersistence::Unlogged : Statement\SequencePersistence::Temporary);
        try {
            return new Statement\CreateSequenceStatement($origin, self::name($source, $context), $persistence, self::conditional($source), self::options($source, $context));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceDefinition, $source, $error);
        }
    }

    /**
     * ALTER SEQUENCE [IF EXISTS] name options.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Origin $origin, Node $source, QueryContext $context): Statement\AlterSequenceStatement
    {
        try {
            return new Statement\AlterSequenceStatement($origin, self::name($source, $context), self::conditional($source), Collections::nonEmpty(self::options($source, $context)));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceDefinition, $source, $error);
        }
    }

    /**
     * Reads the sequence name.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function name(Node $source, QueryContext $context): QualifiedName
    {
        return ObjectAddresses::name(Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A sequence command requires its name.'), $context, 3);
    }

    /**
     * IF NOT EXISTS and IF EXISTS are the only keywords written directly after SEQUENCE.
     */
    public static function conditional(Node $source): bool
    {
        $words = [];
        foreach ($source->children as $child) {
            if ($child instanceof Token) {
                $words[] = strtoupper($child->text);
            }
        }
        return in_array('EXISTS', $words, true);
    }

    /**
     * Reads every option in request order.
     * @return list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function options(Node $source, QueryContext $context): array
    {
        return array_map(static fn (Node $option) => self::option($option, $context), Tree::outer($source, ['SeqOptElem']));
    }

    /**
     * Classifies one option; SEQUENCE NAME only names the sequence of an identity column.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(Node $option, QueryContext $context): Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity
    {
        $words = ObjectAddresses::words($option);
        $number = Tree::child($option, ['NumericOnly']);
        $attribute = self::ATTRIBUTES[$words[0]] ?? null;
        try {
            if ($attribute !== null) {
                return new Identity\SequenceValueChange(Identity\SequenceAttribute::from($attribute), self::number($number ?? $option));
            }
            return match ($words[0]) {
                'AS' => new Identity\SequenceStorage((new TypeReader(Dialect::PostgreSql))->read(Tree::child($option, ['SimpleTypename']) ?? throw new UnclassifiedSql('AS requires a type.'))),
                'CYCLE' => Identity\SequenceFlag::Cycle,
                'NO' => Identity\SequenceFlag::from('NO ' . ($words[1] ?? '')),
                'OWNED' => new Identity\SetSequenceOwner(self::owner(Tree::child($option, ['any_name']) ?? throw new UnclassifiedSql('OWNED BY requires a column.'), $context)),
                'RESTART' => new Identity\RestartIdentity($number === null ? null : self::number($number)),
                'SEQUENCE', 'LOGGED', 'UNLOGGED' => throw new InvalidSql(InputViolation::SequenceDefinition, $option),
                default => throw new UnclassifiedSql('Unclassified sequence option: ' . Tree::text($option)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceDefinition, $option, $error);
        }
    }

    /**
     * OWNED BY NONE detaches the sequence; anything else is a column path.
     * @throws InvalidSql
     */
    public static function owner(Node $name, QueryContext $context): ?QualifiedName
    {
        $parts = $context->tables->identifiers->parts($name);
        return $parts === ['none'] ? null : ObjectAddresses::name($name, $context, 4);
    }

    /**
     * Folds the sign and the digit separators into one integer literal that fits a signed 64-bit integer.
     * @throws InvalidSql
     */
    public static function number(Node $number): Literal
    {
        $tokens = $number->tokens();
        $digits = $tokens[count($tokens) - 1];
        $sign = count($tokens) === 2 && $tokens[0]->text === '-' ? '-' : '';
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($digits);
        $text = $sign . str_replace('_', '', $digits->text);
        if (!$literal instanceof Literal || count($tokens) > 2 || preg_match('/^-?[0-9]+$/D', $text) !== 1) {
            throw new InvalidSql(InputViolation::SequenceDefinition, $number);
        }
        try {
            $value = new Literal($literal->facts, $digits, $literal->literalKind, $text);
            Statement\SequenceInvariant::integer($value);
            return $value;
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceDefinition, $number, $error);
        }
    }
}
