<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\BaseTypeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ConfigurationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ParserAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TemplateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TextArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * Reports the attributes of a definition that their command does not recognize or cannot read.
 *
 * Rule: PG-DEFINE-ATTRIBUTE-001. An attribute the command does not
 * recognize draws the command's message: a warning for an operator, an
 * aggregate and a base type, an error for a range type, a collation and a
 * text search parser, template or configuration; a text search dictionary
 * passes every other attribute to its template. A recognized attribute whose
 * value its `defGet*` function rejects draws that function's message. A read
 * value is further checked where the command checks it without a catalog:
 * SETOF in an operator argument type, a type category outside printable
 * ASCII, and the builtin collation provider before PostgreSQL 17. A base
 * type, a range type and a collation take each attribute once (`analyze` and
 * `analyse` are one attribute). Terminates: one pass over the attributes.
 * Source: `src/backend/commands/define.c`, `DefineOperator` in `src/backend/commands/operatorcmds.c`,
 * `DefineAggregate` in `src/backend/commands/aggregatecmds.c`, `DefineType` and `DefineRange` in `src/backend/commands/typecmds.c`,
 * `DefineCollation` in `src/backend/commands/collationcmds.c` and `src/backend/commands/tsearchcmds.c` of PostgreSQL 16 and 17.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AttributeChecks
{
    /**
     * The problem each command reports for an attribute it does not recognize.
     */
    private const UNRECOGNIZED = [
        OperatorAttribute::class => AttributeProblemKind::UnrecognizedOperatorAttribute,
        AggregateAttribute::class => AttributeProblemKind::UnrecognizedAggregateAttribute,
        BaseTypeAttribute::class => AttributeProblemKind::UnrecognizedTypeAttribute,
        RangeAttribute::class => AttributeProblemKind::UnrecognizedRangeAttribute,
        CollationAttribute::class => AttributeProblemKind::UnrecognizedCollationAttribute,
        ParserAttribute::class => AttributeProblemKind::UnrecognizedParserParameter,
        TemplateAttribute::class => AttributeProblemKind::UnrecognizedTemplateParameter,
        ConfigurationAttribute::class => AttributeProblemKind::UnrecognizedConfigurationParameter,
    ];

    /**
     * Reports the problems of each attribute and, when each attribute is taken once, every repetition.
     *
     * @param class-string<KnownAttribute> $command The attribute set of the command
     * @param list<Attribute> $attributes
     */
    public function derive(Derivation $derivation, string $command, array $attributes, bool $once): void
    {
        $release = $derivation->context->profile->grammar;
        $seen = [];
        foreach ($attributes as $attribute) {
            $known = $attribute->known;
            $problem = $known === null ? $this->unrecognized($command, $attribute) : $this->value($attribute, $known, $release);
            if ($problem !== null) {
                $derivation->report($problem);
            }
            $key = $known === BaseTypeAttribute::Analyse ? BaseTypeAttribute::Analyze->value : $known?->text();
            if ($once && $key !== null && isset($seen[$key])) {
                $derivation->report(new AttributeProblem(AttributeProblemKind::Conflicting));
            }
            $seen[$key ?? ''] = true;
        }
    }

    /**
     * Answers the problem the command reports for an attribute it does not recognize, or null when it passes the attribute on.
     *
     * @param class-string<KnownAttribute> $command
     */
    public function unrecognized(string $command, Attribute $attribute): ?AttributeProblem
    {
        $kind = self::UNRECOGNIZED[$command] ?? null;

        return $kind === null ? null : new AttributeProblem($kind, [$attribute->name->value]);
    }

    /**
     * Answers the problem with the value of a recognized attribute, or null.
     */
    public function value(Attribute $attribute, KnownAttribute $known, GrammarRelease $release): ?AttributeProblem
    {
        $value = $attribute->value;
        if (!$value instanceof AttributeArgument) {
            return $this->rejected($known->reading(), $attribute->name->value, $value);
        }
        $text = new ArgumentText();

        return match (true) {
            ($known === OperatorAttribute::Leftarg || $known === OperatorAttribute::Rightarg) && $value instanceof TypeArgument && $value->type instanceof TypeName && $value->type->setOf => new AttributeProblem(AttributeProblemKind::SetofArgument),
            $known === BaseTypeAttribute::Category && $value instanceof TextArgument && !$this->printable($text->text($value->text)) => new AttributeProblem(AttributeProblemKind::InvalidCategory, [$text->text($value->text)]),
            $value instanceof ChoiceArgument && $value->choice === CollationProvider::Builtin && $release === GrammarRelease::PostgreSql166 => new AttributeProblem(AttributeProblemKind::InvalidProvider, [$text->text($value->written)]),
            default => null,
        };
    }

    /**
     * Answers the problem the command reports for a value it cannot read, or null for a value it does not read.
     */
    public function rejected(Reading $reading, string $name, TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant|null $value): ?AttributeProblem
    {
        if ($reading === Reading::Ignored) {
            return null;
        }
        if ($value === null) {
            return new AttributeProblem($reading === Reading::Integer ? AttributeProblemKind::NotAnInteger : AttributeProblemKind::MissingParameter, [$name]);
        }
        if ($reading->kind() !== null) {
            return new AttributeProblem(AttributeProblemKind::NotAName, [$name]);
        }
        $text = (new ArgumentText())->text($value);

        return match (true) {
            $reading === Reading::Type => new AttributeProblem(AttributeProblemKind::NotATypeName, [$name]),
            $reading === Reading::Boolean => new AttributeProblem(AttributeProblemKind::NotABoolean, [$name]),
            $reading === Reading::Integer, $reading === Reading::Length && $value instanceof SignedNumber => new AttributeProblem(AttributeProblemKind::NotAnInteger, [$name]),
            $reading === Reading::Length => new AttributeProblem(AttributeProblemKind::InvalidLength, [$name, $text]),
            $reading === Reading::Parallelism => new AttributeProblem(AttributeProblemKind::InvalidParallel),
            $reading === Reading::FinalModification => new AttributeProblem(AttributeProblemKind::InvalidModify, [$name]),
            $reading === Reading::Alignment => new AttributeProblem(AttributeProblemKind::InvalidAlignment, [$text]),
            $reading === Reading::Storage => new AttributeProblem(AttributeProblemKind::InvalidStorage, [$text]),
            $reading === Reading::Provider => new AttributeProblem(AttributeProblemKind::InvalidProvider, [$text]),
            default => null,
        };
    }

    /**
     * Tells whether the first character of a type category is printable ASCII, as the server requires.
     */
    public function printable(string $category): bool
    {
        $first = $category === '' ? 0 : ord($category[0]);

        return $first >= 32 && $first <= 126;
    }
}
