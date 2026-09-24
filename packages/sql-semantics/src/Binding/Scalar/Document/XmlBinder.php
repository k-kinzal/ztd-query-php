<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds PostgreSQL's SQL/XML predicates and conversions and hands the constructors to their own binder.
 * @visibility SqlSemantics
 */
final class XmlBinder
{
    /**
     * Recognizes the XML functions by their leading keyword; returns null for anything else.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        if ($scope->identifiers->dialect !== Dialect::PostgreSql) {
            return null;
        }
        $first = $source->children[0] ?? null;
        if ($source->name !== 'func_expr_common_subexpr' || !$first instanceof Token) {
            return null;
        }
        return match (strtoupper($first->text)) {
            'XMLEXISTS' => self::exists($source, $scope),
            'XMLPARSE' => self::parse($source, $scope),
            'XMLSERIALIZE' => self::serialize($source, $scope),
            'XMLROOT' => self::root($source, $scope),
            'XMLELEMENT' => XmlConstructorBinder::element($source, $scope),
            'XMLFOREST' => XmlConstructorBinder::forest($source, $scope),
            'XMLPI' => XmlConstructorBinder::instruction($source, $scope),
            'XMLCONCAT' => XmlConstructorBinder::concatenation($source, $scope),
            default => null,
        };
    }

    /**
     * Binds PostgreSQL `value IS [NOT] DOCUMENT`, which is NULL for a NULL value; returns null for any other expression.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function document(Node $source, Scope $scope): ?Xml\DocumentPredicate
    {
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || !in_array($source->name, ['a_expr', 'b_expr'], true)) {
            return null;
        }
        $children = Tree::significant($source);
        $last = $children[count($children) - 1] ?? null;
        $value = $children[0] ?? null;
        if (!$last instanceof Token || strtoupper($last->text) !== 'DOCUMENT' || !$value instanceof Node) {
            return null;
        }
        $bound = (new ExpressionBinder())->bind($value, $scope);
        return new Xml\DocumentPredicate(self::facts('boolean', [$bound], NullFacts::strict([$bound])), $source, $bound, count($children) === 4);
    }

    /**
     * Binds XMLEXISTS with the passing mode written before and after the document.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function exists(Node $source, Scope $scope): Xml\XmlExistence
    {
        $path = Tree::child($source, ['c_expr']);
        $argument = Tree::child($source, ['xmlexists_argument']);
        $document = $argument === null ? null : Tree::child($argument, ['c_expr']);
        if ($path === null || $argument === null || $document === null) {
            Tree::invalid($source, 'XMLEXISTS path and document');
        }
        $modes = [PassingMode::Default, PassingMode::Default];
        $side = 0;
        foreach ($argument->children as $child) {
            if ($child === $document) {
                $side = 1;
            } elseif ($child instanceof Node && $child->name === 'xml_passing_mech') {
                $modes[$side] = PassingMode::from(strtoupper(Tree::text($child)));
            }
        }
        $binder = new ExpressionBinder();
        $operands = [$binder->bind($path, $scope), $binder->bind($document, $scope)];
        return new Xml\XmlExistence(self::facts('boolean', $operands, NullFacts::strict($operands)), $source, $operands[0], $operands[1], $modes[0], $modes[1]);
    }

    /**
     * Binds XMLPARSE; STRIP WHITESPACE is the default.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function parse(Node $source, Scope $scope): Xml\XmlParse
    {
        $value = (new ExpressionBinder())->bind(Tree::child($source, ['a_expr']) ?? Tree::invalid($source, 'XMLPARSE value'), $scope);
        $whitespace = Tree::child($source, ['xml_whitespace_option']);
        return new Xml\XmlParse(self::facts('xml', [$value], NullFacts::strict([$value])), $source, self::option($source), $value, $whitespace !== null && strtoupper($whitespace->tokens()[0]->text) === 'PRESERVE');
    }

    /**
     * Binds XMLSERIALIZE; the target must be a character string type or a type the binder cannot see, such as a domain.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function serialize(Node $source, Scope $scope): Xml\XmlSerialization
    {
        $value = (new ExpressionBinder())->bind(Tree::child($source, ['a_expr']) ?? Tree::invalid($source, 'XMLSERIALIZE value'), $scope);
        $typeName = Tree::child($source, ['SimpleTypename']) ?? Tree::invalid($source, 'XMLSERIALIZE type');
        $target = (new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql))->read($typeName);
        if (!$target->identity instanceof \SqlSemantics\Type\Identity\StringStorage && !$target->identity instanceof \SqlSemantics\Type\Identity\NamedIdentity) {
            throw new InvalidSql(InputViolation::XmlSerializationTarget, $typeName);
        }
        $indent = Tree::child($source, ['xml_indent_option']);
        $nullability = NullFacts::strict([$value]);
        return new Xml\XmlSerialization(new ExpressionFacts($target, $nullability, NullFacts::extensions([$value], $nullability)), $source, self::option($source), $value, $target, $indent !== null && strtoupper($indent->tokens()[0]->text) === 'INDENT');
    }

    /**
     * Binds XMLROOT; VERSION NO VALUE removes the version and an omitted STANDALONE leaves it unchanged.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function root(Node $source, Scope $scope): Xml\XmlRoot
    {
        $binder = new ExpressionBinder();
        $value = $binder->bind(Tree::child($source, ['a_expr']) ?? Tree::invalid($source, 'XMLROOT value'), $scope);
        $versionNode = Tree::child(Tree::child($source, ['xml_root_version']) ?? Tree::invalid($source, 'XMLROOT version'), ['a_expr']);
        $version = $versionNode === null ? null : $binder->bind($versionNode, $scope);
        $standaloneNode = Tree::child($source, ['opt_xml_root_standalone']);
        $words = $standaloneNode === null ? [] : array_map(static fn (Token $token): string => strtoupper($token->text), array_slice($standaloneNode->tokens(), 2));
        return new Xml\XmlRoot(self::facts('xml', [$value], NullFacts::strict([$value])), $source, $value, $version, Xml\XmlStandalone::from(implode(' ', $words)));
    }

    /**
     * Reads DOCUMENT or CONTENT.
     */
    public static function option(Node $source): Xml\XmlOption
    {
        return Xml\XmlOption::from(strtoupper(Tree::text(Tree::child($source, ['document_or_content']) ?? Tree::invalid($source, 'XML option'))));
    }

    /**
     * Builds facts of a builtin result type whose NULL extension follows the operands.
     * @param list<Expression> $operands
     */
    public static function facts(string $type, array $operands, Nullability $nullability): ExpressionFacts
    {
        return new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, $type), $nullability, NullFacts::extensions($operands, $nullability));
    }
}
