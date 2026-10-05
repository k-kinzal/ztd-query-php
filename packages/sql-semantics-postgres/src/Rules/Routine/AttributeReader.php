<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Choice;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\BaseTypeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ConfigurationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ParserAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TemplateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TextArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reads the value of a definition attribute the way its command does.
 *
 * Rule: PG-DEFINE-READ-001. The attribute name is looked up, respecting
 * case, in the set of the defining command. A recognized attribute's value
 * is read as its reading prescribes when the `defGet*` function of that
 * reading accepts it: a name from anything but a number or no value, a type
 * from a type name, string or keyword, a Boolean from no value, 0, 1 or the
 * texts true, false, on and off, an integer from a 32-bit integer, text from
 * any value, a type length from a 32-bit integer or `variable`, and a choice
 * from a text that names a member. Any other value is kept as written, and
 * the defining command's checks report it. A plain name written as a type
 * name is kept as a dotted name. Lowering and the attribute constructor use
 * the same reading, so the reading of a written value is unique. Terminates:
 * no recursion.
 * Source: `src/backend/commands/define.c` of PostgreSQL 17, https://www.postgresql.org/docs/17/sql-createoperator.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AttributeReader
{
    /**
     * The attribute set of each command that defines an object from attributes, by object kind.
     */
    private const COMMANDS = [
        'AGGREGATE' => AggregateAttribute::class,
        'OPERATOR' => OperatorAttribute::class,
        'TYPE' => BaseTypeAttribute::class,
        'TEXT SEARCH PARSER' => ParserAttribute::class,
        'TEXT SEARCH DICTIONARY' => DictionaryAttribute::class,
        'TEXT SEARCH TEMPLATE' => TemplateAttribute::class,
        'TEXT SEARCH CONFIGURATION' => ConfigurationAttribute::class,
        'COLLATION' => CollationAttribute::class,
    ];

    /**
     * Answers the attribute set of the command that defines objects of a kind.
     *
     * @return class-string<KnownAttribute>
     */
    public function command(ObjectKind $kind): string
    {
        $command = self::COMMANDS[$kind->value] ?? null;
        Check::input($command !== null, 'Only an aggregate, an operator, a type, a text search object and a collation are defined with attributes.');

        return $command;
    }

    /**
     * Builds an attribute of the command whose attribute set is given.
     *
     * @param class-string<KnownAttribute> $command The attribute set of the command
     */
    public function attribute(string $command, Name $name, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): Attribute
    {
        $known = $command::named($name->value);

        return new Attribute($name, $known, $known === null ? $value : $this->read($known->reading(), $value));
    }

    /**
     * Reads a written value; a value the reading rejects is answered as written.
     */
    public function read(Reading $reading, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): AttributeArgument|TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null
    {
        $kind = $reading->kind();
        $choices = $reading->choices();
        if ($kind !== null) {
            return $this->name($kind, $value);
        }
        if ($choices !== null) {
            return $this->choice($choices, $value);
        }

        return match (true) {
            $reading === Reading::Type => $value instanceof TypeName || $value instanceof StringConstant || $value instanceof KeywordWord ? new TypeArgument($value) : $value,
            $reading === Reading::Boolean => $this->boolean($value) !== null ? new BooleanArgument($value) : $value,
            $reading === Reading::Integer => $value instanceof SignedNumber && (new ArgumentText())->integer($value) !== null ? new IntegerArgument($value) : $value,
            $reading === Reading::Text => $value === null ? null : new TextArgument($value),
            $reading === Reading::Length => $this->length($value),
            default => $value,
        };
    }

    /**
     * Reads a value as the name of an object of a kind; no value and a number are not names.
     */
    public function name(ObjectKind $kind, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): NameArgument|SignedNumber|null
    {
        if ($value === null || $value instanceof SignedNumber) {
            return $value;
        }
        $designation = $value instanceof TypeName ? $value->designation : null;

        return new NameArgument($kind, $value instanceof TypeName && $designation instanceof NamedDesignation && $this->plain($value) ? $designation->name : $value);
    }

    /**
     * Reads a value as one of a set of words.
     *
     * @param class-string<Choice> $choices
     */
    public function choice(string $choices, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): ChoiceArgument|TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null
    {
        $choice = $value === null ? null : $choices::read((new ArgumentText())->text($value));

        return $value !== null && $choice !== null ? new ChoiceArgument($value, $choice) : $value;
    }

    /**
     * Reads a value as a type length.
     */
    public function length(TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): LengthArgument|TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null
    {
        $text = new ArgumentText();
        $length = match (true) {
            $value instanceof SignedNumber => $text->integer($value) !== null,
            $value instanceof StringConstant, $value instanceof TypeName => strtolower($text->text($value)) === 'variable',
            default => false,
        };

        return $length && ($value instanceof SignedNumber || $value instanceof StringConstant || $value instanceof TypeName) ? new LengthArgument($value) : $value;
    }

    /**
     * Answers the Boolean a value reads as, or null when it is not a Boolean; no value is true.
     */
    public function boolean(TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): ?bool
    {
        if ($value === null) {
            return true;
        }
        $text = new ArgumentText();
        $integer = $value instanceof SignedNumber ? $text->integer($value) : null;
        if ($integer !== null) {
            return [0 => false, 1 => true][$integer] ?? null;
        }

        return ['true' => true, 'false' => false, 'on' => true, 'off' => false][strtolower($text->text($value))] ?? null;
    }

    /**
     * Tells whether a type name is a plain, possibly qualified, name without modifiers, array bounds or SETOF.
     */
    public function plain(TypeName $type): bool
    {
        return $type->designation instanceof NamedDesignation && $type->designation->modifiers === [] && !$type->setOf && $type->array === null;
    }
}
