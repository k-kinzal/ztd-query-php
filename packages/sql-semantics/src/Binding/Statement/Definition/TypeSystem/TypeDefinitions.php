<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds shell, composite, and enum type definitions and the ALTER TYPE forms for enum labels and composite attributes.
 * @visibility SqlSemantics
 */
final class TypeDefinitions
{
    /**
     * CREATE TYPE with no body declares a shell; AS (...) a composite; AS ENUM (...) an enum.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $name = self::name($source, $context);
        $tail = self::tail($source, 2);
        return match (true) {
            $tail === [] => new Statement\CreateShellTypeStatement($origin, $name),
            ($tail[1] ?? '') === 'ENUM' => self::enum($origin, $name, Tree::child($source, ['opt_enum_val_list']), $source, $context),
            ($tail[1] ?? '') === 'RANGE' => TypeOptions::range($origin, $name, $source, $context),
            $tail[0] === '(' => TypeOptions::base($origin, $name, $source, $context),
            ($tail[1] ?? '') === '(' => new Statement\CreateCompositeTypeStatement($origin, $name, self::attributes($source, $context)),
            default => throw new UnclassifiedSql('Unclassified CREATE TYPE form.'),
        };
    }

    /**
     * The uppercased words after the command keywords and the object name.
     * @return list<string>
     */
    public static function tail(Node $source, int $prefix): array
    {
        $name = Tree::child($source, ['any_name']);
        return array_slice(ObjectAddresses::words($source), $prefix + ($name === null ? 0 : count($name->tokens())));
    }

    /**
     * Composite attributes cannot be SETOF and cannot repeat a name.
     * @return list<Composite\CompositeAttribute>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function attributes(Node $source, QueryContext $context): array
    {
        $attributes = array_map(static fn (Node $element): Composite\CompositeAttribute => self::attribute($element, $context), Tree::outer($source, ['TableFuncElement']));
        $names = array_map(static fn (Composite\CompositeAttribute $attribute): string => $attribute->name, $attributes);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidSql(InputViolation::CompositeAttribute, $source);
        }
        return $attributes;
    }

    /**
     * Reads one attribute declaration with its optional collation.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function attribute(Node $element, QueryContext $context): Composite\CompositeAttribute
    {
        $type = Tree::child($element, ['Typename']) ?? throw new UnclassifiedSql('An attribute requires its type.');
        if (self::setOf($type)) {
            throw new InvalidSql(InputViolation::CompositeAttribute, $type);
        }
        $name = $context->tables->identifiers->name(($element->tokens()[0] ?? throw new UnclassifiedSql('An attribute requires its name.')));
        return new Composite\CompositeAttribute($name, (new TypeReader(Dialect::PostgreSql))->read($type), self::collation($element, $context));
    }

    /**
     * Enum labels are decoded string constants, unique and at most 63 bytes long.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function enum(Origin $origin, QualifiedName $name, ?Node $list, Node $source, QueryContext $context): Statement\CreateEnumTypeStatement
    {
        $labels = $list === null ? [] : array_map(static fn (Node $label): string => self::label($label, $context), Tree::outer($list, ['Sconst']));
        try {
            Enumeration\EnumLabels::labels($labels);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::EnumLabel, $source, $error);
        }
        return new Statement\CreateEnumTypeStatement($origin, $name, $labels);
    }

    /**
     * ADD VALUE and RENAME VALUE change labels; the server does not implement DROP VALUE.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alterEnum(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $type = self::name($source, $context);
        $labels = array_map(static fn (Node $label): string => self::label($label, $context), Tree::outer($source, ['Sconst']));
        $words = self::tail($source, 2);
        try {
            return match ($words[0] ?? '') {
                'RENAME' => new Statement\RenameEnumLabelStatement($origin, $type, $labels[0] ?? '', $labels[1] ?? ''),
                'ADD' => new Statement\AddEnumLabelStatement($origin, $type, $labels[0] ?? '', (Tree::child($source, ['opt_if_not_exists'])?->tokens() ?? []) !== [], self::position($words, $labels)),
                default => throw new InvalidSql(InputViolation::EnumLabel, $source),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::EnumLabel, $source, $error);
        }
    }

    /**
     * BEFORE or AFTER names the existing neighbor of a new label.
     * @param list<string> $words
     * @param list<string> $labels
     * @throws InvalidStructure
     */
    public static function position(array $words, array $labels): ?Enumeration\EnumLabelPosition
    {
        $placement = in_array('BEFORE', $words, true) ? Enumeration\EnumLabelPlacement::Before : (in_array('AFTER', $words, true) ? Enumeration\EnumLabelPlacement::After : null);
        return $placement === null ? null : new Enumeration\EnumLabelPosition($placement, $labels[1] ?? '');
    }

    /**
     * Reads ADD, DROP, and ALTER ATTRIBUTE commands in order.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alterComposite(Origin $origin, Node $source, QueryContext $context): Statement\AlterCompositeTypeStatement
    {
        $changes = array_map(static fn (Node $command): Composite\AttributeChange => self::change($command, $context), Tree::outer($source, ['alter_type_cmd']));
        try {
            return new Statement\AlterCompositeTypeStatement($origin, self::name($source, $context), \SqlSemantics\Model\Validation\Collections::nonEmpty($changes));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CompositeAttribute, $source, $error);
        }
    }

    /**
     * Reads one attribute command; SET DATA before TYPE is noise.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function change(Node $command, QueryContext $context): Composite\AttributeChange
    {
        $behavior = Domains::behavior($command);
        $words = ObjectAddresses::words($command);
        $name = static fn (): string => $context->tables->identifiers->name((Tree::child($command, ['ColId']) ?? throw new UnclassifiedSql('An attribute command requires its attribute.'))->tokens()[0]);
        return match ($words[0] ?? '') {
            'ADD' => new Composite\AddAttribute(self::attribute(Tree::child($command, ['TableFuncElement']) ?? throw new UnclassifiedSql('ADD ATTRIBUTE requires its declaration.'), $context), $behavior),
            'DROP' => new Composite\DropAttribute($name(), ($words[2] ?? '') === 'IF', $behavior),
            default => new Composite\RetypeAttribute($name(), (new TypeReader(Dialect::PostgreSql))->read(Tree::child($command, ['Typename']) ?? throw new UnclassifiedSql('ALTER ATTRIBUTE requires its type.')), self::collation($command, $context), $behavior),
        };
    }

    /**
     * Reads the type name after CREATE TYPE or ALTER TYPE.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function name(Node $source, QueryContext $context): QualifiedName
    {
        return ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A type command requires its type name.'), $context, 2);
    }

    /**
     * Reads an optional COLLATE clause.
     * @throws InvalidSql
     */
    public static function collation(Node $source, QueryContext $context): ?QualifiedName
    {
        $clause = Tree::child($source, ['opt_collate_clause']);
        $name = $clause === null ? null : Tree::child($clause, ['any_name']);
        return $name === null ? null : ObjectAddresses::name($name, $context, 2);
    }

    /**
     * Decodes one string constant.
     * @throws UnclassifiedSql
     */
    public static function label(Node $constant, QueryContext $context): string
    {
        return FieldSpelling::read($constant->tokens()[0] ?? throw new UnclassifiedSql('A label requires its string constant.'), $context->tables->identifiers);
    }

    /**
     * A type declaration spelled with SETOF.
     */
    public static function setOf(Node $type): bool
    {
        return strtoupper($type->tokens()[0]->text ?? '') === 'SETOF';
    }
}
