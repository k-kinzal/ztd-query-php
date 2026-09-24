<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Routine\Parameters;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Converts definition arguments to the typed value their attribute takes, as the server converts them.
 * @visibility SqlSemantics
 */
final class DefinitionArguments
{
    /**
     * Catalog names of the built-in types the grammar spells with keywords.
     */
    public const SYSTEM = ['integer' => 'int4', 'smallint' => 'int2', 'bigint' => 'int8', 'real' => 'float4', 'double precision' => 'float8', 'char' => 'bpchar', 'varchar' => 'varchar', 'numeric' => 'numeric', 'boolean' => 'bool', 'bit' => 'bit', 'varbit' => 'varbit', 'timestamp' => 'timestamp', 'timestamptz' => 'timestamptz', 'time' => 'time', 'timetz' => 'timetz', 'interval' => 'interval', 'json' => 'json'];

    /**
     * Builds the option for an element; an absent argument sets a Boolean attribute and leaves others null.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(DefinitionElement $element, DefinitionAttribute $attribute, QueryContext $context): DefinitionOption
    {
        $argument = $element->argument;
        $kind = $attribute->kind();
        if ($argument === null) {
            return new DefinitionOption($attribute, $kind === DefinitionKind::Boolean ? true : null);
        }
        $value = match ($kind) {
            DefinitionKind::Name => self::name($argument, $context),
            DefinitionKind::Operator => self::operator($argument, $context),
            DefinitionKind::Type => self::type($argument, $context),
            DefinitionKind::Boolean => self::boolean($argument, $context),
            DefinitionKind::Integer => self::integer($argument),
            DefinitionKind::Length => in_array(strtolower(self::text($argument, $context)), ['variable', '-1'], true) ? -1 : self::integer($argument),
            DefinitionKind::Text => self::text($argument, $context),
            DefinitionKind::Choice => $attribute->choose(self::text($argument, $context)),
        };
        if ($value === null || !$kind->accepts($value)) {
            throw new InvalidSql(InputViolation::DefinitionArgument, $argument);
        }
        return new DefinitionOption($attribute, $value);
    }

    /**
     * Converts an argument that a definition must spell; only a Boolean attribute may omit it.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function present(DefinitionElement $element, DefinitionAttribute $attribute, QueryContext $context): DefinitionOption
    {
        $option = self::option($element, $attribute, $context);
        return $option->value === null ? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source) : $option;
    }

    /**
     * A name is written as a plain qualified name, a keyword, a string, or an operator.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function name(Node $argument, QueryContext $context): QualifiedName
    {
        $operator = Tree::child($argument, ['qual_all_Op']);
        if ($operator !== null) {
            return self::operator($argument, $context);
        }
        return new QualifiedName(self::words($argument, $context) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $argument));
    }

    /**
     * A schema-scoped object named by an element that must spell its argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function objectName(DefinitionElement $element, QueryContext $context): QualifiedName
    {
        $name = self::name($element->argument ?? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source), $context);
        return count($name->parts) > 2 ? throw new InvalidSql(InputViolation::CatalogObjectName, $element->source) : $name;
    }

    /**
     * An operator is written as a symbol, as OPERATOR(schema.symbol), or as a string holding a symbol.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function operator(Node $argument, QueryContext $context): QualifiedName
    {
        $operator = Tree::child($argument, ['qual_all_Op']);
        if ($operator === null) {
            return new QualifiedName(self::words($argument, $context) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $argument));
        }
        $symbol = Tree::outer($operator, ['any_operator'])[0] ?? $operator;
        return ObjectAddresses::name($symbol, $context, 2);
    }

    /**
     * A type is a declaration, a column-type reference, or a name written as a keyword or string.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function type(Node $argument, QueryContext $context): TypeDescriptor|ColumnTypeReference
    {
        $declaration = Tree::child($argument, ['func_type']);
        if ($declaration !== null) {
            return Parameters::type($declaration, $context);
        }
        if (Tree::child($argument, ['qual_all_Op', 'NumericOnly']) !== null) {
            throw new InvalidSql(InputViolation::DefinitionArgument, $argument);
        }
        return new TypeDescriptor(Dialect::PostgreSql, new NamedIdentity(new QualifiedName([self::text($argument, $context)])));
    }

    /**
     * TRUE, FALSE, ON, and OFF in any case, or the integers 0 and 1.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function boolean(Node $argument, QueryContext $context): bool
    {
        $number = Tree::child($argument, ['NumericOnly']);
        $text = $number === null ? strtolower(self::text($argument, $context)) : (string) self::integer($argument);
        return match ($text) {
            'true', 'on', '1' => true,
            'false', 'off', '0' => false,
            default => throw new InvalidSql(InputViolation::DefinitionArgument, $argument),
        };
    }

    /**
     * An integer constant with an optional sign.
     * @throws InvalidSql
     */
    public static function integer(Node $argument): int
    {
        $number = Tree::child($argument, ['NumericOnly']);
        $text = $number === null ? '' : str_replace([' ', '_'], '', Tree::text($number));
        if (preg_match('/^[+-]?[0-9]+$/D', $text) !== 1 || abs((float) $text) > 2147483647) {
            throw new InvalidSql(InputViolation::DefinitionArgument, $argument);
        }
        return (int) $text;
    }

    /**
     * The text the server reads: a decoded string, a number, a name joined by dots, or a keyword.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function text(Node $argument, QueryContext $context): string
    {
        $number = Tree::child($argument, ['NumericOnly']);
        if ($number !== null) {
            $spelling = str_replace([' ', '_'], '', Tree::text($number));
            return preg_match('/^[+-]?[0-9]+$/D', $spelling) === 1 && abs((float) $spelling) <= 2147483647 ? (string) (int) $spelling : $spelling;
        }
        $operator = Tree::child($argument, ['qual_all_Op']);
        if ($operator !== null) {
            return implode('.', self::operator($argument, $context)->parts);
        }
        $words = self::words($argument, $context) ?? self::system($argument);
        return implode('.', $words ?? throw new InvalidSql(InputViolation::DefinitionArgument, $argument));
    }

    /**
     * A built-in type keyword spelled as the server spells its catalog name, such as pg_catalog.bpchar for CHAR.
     * @return non-empty-list<string>|null
     */
    public static function system(Node $argument): ?array
    {
        $declaration = Tree::outer($argument, ['Typename'])[0] ?? null;
        if ($declaration === null || array_filter(Tree::outer($declaration, ['GenericType', 'opt_array_bounds']), Tree::hasTokens(...)) !== [] || strtoupper($declaration->tokens()[0]->text ?? '') === 'SETOF') {
            return null;
        }
        $name = self::SYSTEM[(new TypeReader(Dialect::PostgreSql))->read($declaration)->name] ?? null;
        return $name === null ? null : ['pg_catalog', $name];
    }

    /**
     * The name components of a plain name, keyword, NONE, or string argument; null for other forms.
     * @return non-empty-list<string>|null
     * @throws UnclassifiedSql
     */
    public static function words(Node $argument, QueryContext $context): ?array
    {
        $constant = Tree::child($argument, ['Sconst']);
        if ($constant !== null) {
            return [TypeDefinitions::label($constant, $context)];
        }
        $declaration = Tree::child($argument, ['func_type']);
        if ($declaration === null) {
            $tokens = $argument->tokens();
            return count($tokens) === 1 && Tree::child($argument, ['NumericOnly', 'qual_all_Op']) === null ? [strtolower($tokens[0]->text)] : null;
        }
        $generic = Tree::outer($declaration, ['GenericType'])[0] ?? null;
        if ($generic === null || count($generic->tokens()) !== count($declaration->tokens()) || Tree::child($generic, ['opt_type_modifiers']) !== null) {
            return null;
        }
        $parts = $context->tables->identifiers->parts($generic);
        return $parts === [] ? null : $parts;
    }
}
