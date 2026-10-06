<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;

/**
 * Reports the attributes a definition lacks or combines in a way its command rejects.
 *
 * Rule: PG-DEFINE-CHECK-001. Attribute names are compared respecting case
 * (an unquoted name is folded to lower case). An operator needs a function
 * (`function` or `procedure`) and, as postfix operators no longer exist, a
 * right argument type. An aggregate needs a state type and a state
 * transition function; the moving-aggregate attributes come with `mstype`,
 * and `mstype` with `msfunc` and `minvfunc`; serialization needs both
 * functions; only an ordered-set aggregate is hypothetical; an old-style
 * aggregate names its input type with `basetype`, which is redundant in the
 * new style. A base type needs input and output functions and a modifier
 * input function for a modifier output function. A range type needs a
 * subtype. A collation copied `from` another takes no other attribute and
 * `locale` excludes `lc_collate` and `lc_ctype`; otherwise its provider
 * (libc when not given) needs `lc_collate` and `lc_ctype` or `locale`, or for
 * ICU and builtin `locale`, and only ICU takes rules or is nondeterministic.
 * A text search parser needs its start, gettoken, end and lextypes methods, a
 * template its lexize method, a dictionary its template, and a configuration
 * a parser or a configuration to copy, but not both. Attribute values and
 * unrecognized names are PG-DEFINE-ATTRIBUTE-001. Terminates: one pass over
 * the attributes.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, https://www.postgresql.org/docs/17/sql-createoperator.html,
 * https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/sql-createcollation.html,
 * `DefineOperator`, `DefineAggregate`, `DefineType`, `DefineRange`, `DefineCollation`, `DefineTSParser`, `DefineTSTemplate`,
 * `DefineTSDictionary` and `DefineTSConfiguration` in `src/backend/commands` of PostgreSQL 17.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DefineChecks
{
    /**
     * The methods a text search parser requires.
     */
    private const PARSER_METHODS = ['start', 'gettoken', 'end', 'lextypes'];

    /**
     * Reports the problems of the attributes and of their combination.
     */
    public function derive(Define $define, Derivation $derivation): void
    {
        $attributes = $define->definition ?? [];
        $command = (new AttributeReader())->command($define->kind);
        (new AttributeChecks())->derive($derivation, $command, $attributes, $define->kind === ObjectKind::Type || $define->kind === ObjectKind::Collation);
        $present = $this->present($attributes);
        $problems = match (true) {
            $define->kind === ObjectKind::Operator => $this->operator($present),
            $define->kind === ObjectKind::Aggregate => $this->aggregate($present, $define->arguments),
            $define->kind === ObjectKind::Type => $define->definition === null ? [] : $this->baseType($present),
            $define->kind === ObjectKind::Collation => $this->collation($present, count($attributes), $derivation->context->profile->grammar),
            default => $this->search($define->kind, $present),
        };
        foreach ($problems as $problem) {
            $derivation->report($problem);
        }
    }

    /**
     * Reports the problems of the attributes of a range type.
     *
     * @param list<Attribute> $attributes
     */
    public function range(array $attributes, Derivation $derivation): void
    {
        (new AttributeChecks())->derive($derivation, RangeAttribute::class, $attributes, true);
        if (!isset($this->present($attributes)['subtype'])) {
            $derivation->report(new AttributeProblem(AttributeProblemKind::MissingSubtype));
        }
    }

    /**
     * Answers the recognized attributes by name; a later attribute replaces an earlier one, as the commands read them.
     *
     * @param list<Attribute> $attributes
     *
     * @return array<string, Attribute>
     */
    public function present(array $attributes): array
    {
        $present = [];
        foreach ($attributes as $attribute) {
            if ($attribute->known !== null) {
                $present[$attribute->name->value] = $attribute;
            }
        }

        return $present;
    }

    /**
     * Answers the missing function and argument types of an operator.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function operator(array $present): array
    {
        $problems = [];
        if (!isset($present['function']) && !isset($present['procedure'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::MissingOperatorFunction);
        }
        if (!isset($present['rightarg'])) {
            $problems[] = new AttributeProblem(isset($present['leftarg']) ? AttributeProblemKind::MissingRightArgument : AttributeProblemKind::MissingArgumentTypes);
        }

        return $problems;
    }

    /**
     * Answers the missing and contradictory attributes of an aggregate.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function aggregate(array $present, ?AggregateArguments $arguments): array
    {
        $problems = [];
        foreach (['stype' => 'stype1', 'sfunc' => 'sfunc1'] as $name => $obsolete) {
            if (!isset($present[$name]) && !isset($present[$obsolete])) {
                $problems[] = new AttributeProblem(AttributeProblemKind::MissingAggregateAttribute, [$name]);
            }
        }
        $moving = isset($present['mstype']);
        $space = $present['msspace'] ?? null;
        $given = ['msfunc' => isset($present['msfunc']), 'minvfunc' => isset($present['minvfunc']), 'mfinalfunc' => isset($present['mfinalfunc']), 'msspace' => $space !== null && (!$space->value instanceof IntegerArgument || $space->value->value() !== 0), 'minitcond' => isset($present['minitcond'])];
        foreach ($given as $name => $set) {
            if ($moving && !$set && in_array($name, ['msfunc', 'minvfunc'], true)) {
                $problems[] = new AttributeProblem(AttributeProblemKind::MissingMovingAttribute, [$name]);
            }
            if (!$moving && $set) {
                $problems[] = new AttributeProblem(AttributeProblemKind::MovingWithoutState, [$name]);
            }
        }
        $hypothetical = ($present['hypothetical'] ?? null)?->value;
        if ($hypothetical instanceof BooleanArgument && $hypothetical->value() && $arguments?->ordered === null) {
            $problems[] = new AttributeProblem(AttributeProblemKind::HypotheticalPlain);
        }
        if ($arguments === null xor isset($present['basetype'])) {
            $problems[] = new AttributeProblem($arguments === null ? AttributeProblemKind::MissingInputType : AttributeProblemKind::RedundantBaseType);
        }
        if (isset($present['serialfunc']) xor isset($present['deserialfunc'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::HalfSerialization);
        }

        return $problems;
    }

    /**
     * Answers the missing support functions of a base type.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function baseType(array $present): array
    {
        $problems = [];
        if (!isset($present['input'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::MissingInputFunction);
        }
        if (!isset($present['output'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::MissingOutputFunction);
        }
        if (isset($present['typmod_out']) && !isset($present['typmod_in'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::ModifierOutputAlone);
        }

        return $problems;
    }

    /**
     * Answers the contradictory and missing attributes of a collation; a provider the server does not know stops the checks, as it does.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function collation(array $present, int $count, GrammarRelease $release): array
    {
        $locale = isset($present['locale']);
        $problems = [];
        if (($locale && (isset($present['lc_collate']) || isset($present['lc_ctype']))) || (isset($present['from']) && $count !== 1)) {
            $problems[] = new AttributeProblem(AttributeProblemKind::Conflicting);
        }
        $written = ($present['provider'] ?? null)?->value;
        $chosen = $written instanceof ChoiceArgument ? $written->choice : null;
        $provider = isset($present['provider']) ? ($chosen instanceof CollationProvider ? $chosen : null) : CollationProvider::Libc;
        if (isset($present['from']) || $provider === null || ($provider === CollationProvider::Builtin && $release === GrammarRelease::PostgreSql166)) {
            return $problems;
        }

        return [...$problems, ...$this->provided($provider, $present)];
    }

    /**
     * Answers the attributes a collation of a provider lacks or may not take.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function provided(CollationProvider $provider, array $present): array
    {
        $problems = [];
        foreach ($provider === CollationProvider::Libc ? ['lc_collate', 'lc_ctype'] : ['locale'] as $name) {
            if (!isset($present['locale']) && !isset($present[$name])) {
                $problems[] = new AttributeProblem(AttributeProblemKind::MissingCollationParameter, [$name]);
            }
        }
        $deterministic = ($present['deterministic'] ?? null)?->value;
        if ($provider !== CollationProvider::Icu && $deterministic instanceof BooleanArgument && !$deterministic->value()) {
            $problems[] = new AttributeProblem(AttributeProblemKind::Nondeterministic);
        }
        if ($provider !== CollationProvider::Icu && isset($present['rules'])) {
            $problems[] = new AttributeProblem(AttributeProblemKind::RulesWithoutIcu);
        }

        return $problems;
    }

    /**
     * Answers the missing or contradictory attributes of a text search object.
     *
     * @param array<string, Attribute> $present
     *
     * @return list<AttributeProblem>
     */
    public function search(ObjectKind $kind, array $present): array
    {
        $problems = [];
        foreach ($kind === ObjectKind::TextSearchParser ? self::PARSER_METHODS : [] as $method) {
            if (!isset($present[$method])) {
                $problems[] = new AttributeProblem(AttributeProblemKind::MissingParserMethod, [$method]);
            }
        }
        $missing = match (true) {
            $kind === ObjectKind::TextSearchTemplate && !isset($present['lexize']) => AttributeProblemKind::MissingLexize,
            $kind === ObjectKind::TextSearchDictionary && !isset($present['template']) => AttributeProblemKind::MissingTemplate,
            $kind === ObjectKind::TextSearchConfiguration && isset($present['parser'], $present['copy']) => AttributeProblemKind::ParserAndCopy,
            $kind === ObjectKind::TextSearchConfiguration && !isset($present['parser']) && !isset($present['copy']) => AttributeProblemKind::MissingParser,
            default => null,
        };
        if ($missing !== null) {
            $problems[] = new AttributeProblem($missing);
        }

        return $problems;
    }
}
