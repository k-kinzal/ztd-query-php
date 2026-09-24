<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Catalog;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog as Address;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the object class and identity a PostgreSQL catalog command names, without resolving the object.
 * @visibility SqlSemantics
 */
final class ObjectAddresses
{
    /**
     * Multi-word object classes, longest first so that prefixes never win.
     */
    public const CLASSES = ['FOREIGN DATA WRAPPER', 'TEXT SEARCH PARSER', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH TEMPLATE', 'TEXT SEARCH CONFIGURATION', 'PROCEDURAL LANGUAGE', 'MATERIALIZED VIEW', 'FOREIGN TABLE', 'EVENT TRIGGER', 'ACCESS METHOD', 'LARGE OBJECT', 'OPERATOR CLASS', 'OPERATOR FAMILY'];

    /**
     * Returns the canonical object class keywords that follow the command verb.
     */
    public static function objectClass(Node $source): string
    {
        $words = self::words($source);
        $start = self::start($source, $words);
        foreach (self::CLASSES as $class) {
            $length = substr_count($class, ' ') + 1;
            if (implode(' ', array_slice($words, $start, $length)) === $class) {
                return $class === 'PROCEDURAL LANGUAGE' ? 'LANGUAGE' : $class;
            }
        }
        return $words[$start] ?? '';
    }

    /**
     * @return list<string> Uppercased token spellings of the statement
     */
    public static function words(Node $source): array
    {
        return array_map(static fn (Token $token): string => strtoupper($token->text), $source->tokens());
    }

    /**
     * Skips the verb, the ON of comments and labels, and a label provider.
     * @param list<string> $words
     */
    public static function start(Node $source, array $words): int
    {
        return match ($words[0] ?? '') {
            'COMMENT' => 2,
            'SECURITY' => 3 + count(Tree::child($source, ['opt_provider'])?->tokens() ?? []),
            default => 1,
        };
    }

    /**
     * Builds the identity for the object class of the statement.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $source, QueryContext $context): ObjectAddress
    {
        $class = self::objectClass($source);
        $identifiers = $context->tables->identifiers;
        $names = Tree::outer($source, ['any_name', 'qualified_name', 'relation_expr', 'name', 'RoleId']);
        $first = $names[0] ?? null;
        $named = Kind\NamedObjectKind::tryFrom($class);
        if ($named !== null) {
            return new Address\NamedIdentity($named, $identifiers->name(($first ?? throw new UnclassifiedSql('A named object requires its name.'))->tokens()[0]));
        }
        $relation = Kind\RelationKind::tryFrom($class);
        if ($relation !== null) {
            return new Address\RelationIdentity($relation, self::relation($first ?? throw new UnclassifiedSql('A relation requires its name.'), $context));
        }
        $schemaObject = Kind\SchemaObjectKind::tryFrom($class);
        if ($schemaObject !== null) {
            return new Address\SchemaObjectIdentity($schemaObject, self::name($first ?? throw new UnclassifiedSql('A schema object requires its name.'), $context, 2));
        }
        $member = Kind\RelationMemberKind::tryFrom($class);
        if ($member !== null) {
            return self::member($member, $source, $names, $context);
        }
        return self::signature($class, $source, $names, $context);
    }

    /**
     * Reads the qualified name inside a relation expression, ignoring ONLY and descendant markers.
     * @throws InvalidSql
     */
    public static function relation(Node $node, QueryContext $context): QualifiedName
    {
        return self::name($node, $context, 3);
    }

    /**
     * Rejects names with more components than the object class allows before any model is built.
     * @throws InvalidSql
     */
    public static function name(Node $node, QueryContext $context, int $depth): QualifiedName
    {
        $name = $node->name === 'relation_expr' ? (Tree::outer($node, ['qualified_name'])[0] ?? $node) : $node;
        $parts = $context->tables->identifiers->parts($name);
        if (count($parts) > $depth) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $node);
        }
        return new QualifiedName($parts);
    }

    /**
     * A column is addressed by its qualified path; other members name themselves and their relation.
     * @param list<Node> $names
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function member(Kind\RelationMemberKind $kind, Node $source, array $names, QueryContext $context): Address\RelationMemberIdentity|Address\DomainConstraintIdentity
    {
        $identifiers = $context->tables->identifiers;
        if ($kind === Kind\RelationMemberKind::Column) {
            $parts = $identifiers->parts($names[0] ?? throw new UnclassifiedSql('A column address requires its qualified name.'));
            if (count($parts) < 2 || count($parts) > 4) {
                throw new InvalidSql(InputViolation::CatalogObjectName, $source);
            }
            return new Address\RelationMemberIdentity($kind, $parts[count($parts) - 1], new QualifiedName(array_slice($parts, 0, -1)));
        }
        $name = $identifiers->name(($names[0] ?? throw new UnclassifiedSql('A relation member requires its name.'))->tokens()[0]);
        $relation = self::relation($names[1] ?? throw new UnclassifiedSql('A relation member requires its relation.'), $context);
        $words = self::words($source);
        $start = self::start($source, $words);
        if ($kind === Kind\RelationMemberKind::Constraint && ($words[$start + 3] ?? '') === 'DOMAIN') {
            return new Address\DomainConstraintIdentity($name, self::name($names[1], $context, 2));
        }
        return new Address\RelationMemberIdentity($kind, $name, $relation);
    }

    /**
     * Reads the classes addressed by a type declaration or an overload signature.
     * @param list<Node> $names
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function signature(string $class, Node $source, array $names, QueryContext $context): ObjectAddress
    {
        $identifiers = $context->tables->identifiers;
        $types = array_map(static fn (Node $type) => (new TypeReader(Dialect::PostgreSql))->read($type), Tree::outer($source, ['Typename']));
        $typeKind = Kind\TypeKind::tryFrom($class);
        if ($typeKind !== null) {
            return $types === [] ? new Address\TypeNameIdentity($typeKind, self::name($names[0] ?? throw new UnclassifiedSql('A type requires its name.'), $context, 2)) : new Address\DeclaredTypeIdentity($typeKind, $types[0]);
        }
        $routineKind = Kind\RoutineKind::tryFrom($class);
        if ($routineKind !== null) {
            return new Address\RoutineIdentity($routineKind, Targets::routine(Tree::child($source, ['function_with_argtypes']) ?? throw new UnclassifiedSql('A routine requires its signature.'), $context));
        }
        $operatorSet = Kind\OperatorSetKind::tryFrom($class);
        if ($operatorSet !== null) {
            return new Address\OperatorSetIdentity($operatorSet, self::name($names[0] ?? throw new UnclassifiedSql('An operator class requires its name.'), $context, 2), $identifiers->name(($names[1] ?? throw new UnclassifiedSql('An operator class requires its access method.'))->tokens()[0]));
        }
        return self::special($class, $source, $types, $names, $context);
    }

    /**
     * Reads aggregates, operators, casts, transforms, and large objects, whose identities have their own shapes.
     * @param list<\SqlSemantics\Type\TypeDescriptor> $types
     * @param list<Node> $names
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function special(string $class, Node $source, array $types, array $names, QueryContext $context): ObjectAddress
    {
        $identifiers = $context->tables->identifiers;
        return match ($class) {
            'AGGREGATE' => new Address\AggregateIdentity(Targets::aggregate(Tree::child($source, ['aggregate_with_argtypes']) ?? throw new UnclassifiedSql('An aggregate requires its signature.'), $context)),
            'OPERATOR' => self::operator(Tree::child($source, ['operator_with_argtypes']) ?? throw new UnclassifiedSql('An operator requires its signature.'), $context),
            'CAST' => new Address\CastIdentity($types[0] ?? throw new UnclassifiedSql('A cast requires its source type.'), $types[1] ?? throw new UnclassifiedSql('A cast requires its target type.')),
            'TRANSFORM' => new Address\TransformIdentity($types[0] ?? throw new UnclassifiedSql('A transform requires its type.'), $identifiers->name(($names[0] ?? throw new UnclassifiedSql('A transform requires its language.'))->tokens()[0])),
            'LARGE OBJECT' => self::largeObject(Tree::child($source, ['NumericOnly']) ?? throw new UnclassifiedSql('A large object requires its identifier.')),
            default => throw new UnclassifiedSql('Unclassified catalog object class: ' . $class),
        };
    }

    /**
     * A binary operator declares both operand types; a unary operator spells the missing one as NONE.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function operator(Node $source, QueryContext $context): Address\OperatorIdentity
    {
        $name = Tree::child($source, ['any_operator']) ?? throw new UnclassifiedSql('An operator requires its symbol.');
        $arguments = Tree::child($source, ['oper_argtypes']) ?? throw new UnclassifiedSql('An operator requires its operand types.');
        $types = array_map(static fn (Node $type) => (new TypeReader(Dialect::PostgreSql))->read($type), Tree::outer($arguments, ['Typename']));
        $words = self::words($arguments);
        if (count($types) === 1 && !in_array('NONE', $words, true)) {
            throw new InvalidSql(InputViolation::OperatorSignature, $source);
        }
        $leftNone = ($words[1] ?? '') === 'NONE';
        return new Address\OperatorIdentity(self::name($name, $context, 2), $leftNone ? null : $types[0], $leftNone ? $types[0] : ($types[1] ?? null));
    }

    /**
     * Object identifiers are unsigned 32-bit integers written without sign or fraction.
     * @throws InvalidSql
     */
    public static function largeObject(Node $source): Address\LargeObjectIdentity
    {
        $digits = str_replace('_', '', Tree::text($source));
        if (preg_match('/^[0-9]+$/D', $digits) !== 1) {
            throw new InvalidSql(InputViolation::LargeObjectId, $source);
        }
        $digits = ltrim($digits, '0');
        if (strlen($digits) > 10) {
            throw new InvalidSql(InputViolation::LargeObjectId, $source);
        }
        try {
            return new Address\LargeObjectIdentity((int) $digits);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::LargeObjectId, $source, $error);
        }
    }

    /**
     * Decodes a label provider written as a bare word or as a string constant.
     */
    public static function provider(Token $token, QueryContext $context): string
    {
        $text = $token->text;
        if (str_starts_with($text, '$')) {
            $tag = substr($text, 0, (int) strpos($text, '$', 1) + 1);
            return substr($text, strlen($tag), -strlen($tag));
        }
        if (preg_match('/^[eE]\'/', $text) === 1) {
            return stripcslashes(str_replace("''", "'", substr($text, 2, -1)));
        }
        if (preg_match('/^[uU]&\'/', $text) === 1) {
            return str_replace("''", "'", substr($text, 3, -1));
        }
        if (str_starts_with($text, "'")) {
            return str_replace("''", "'", substr($text, 1, -1));
        }
        return $context->tables->identifiers->name($token);
    }
}
