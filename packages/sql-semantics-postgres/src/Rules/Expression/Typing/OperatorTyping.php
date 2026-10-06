<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ComparisonTyping;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types operator applications over catalog types.
 *
 * Rule: PG-OPERATOR-TYPING-001. An operator is looked up by name and operand
 * types along the search path; a context declares no operators, so a result
 * is known only when `pg_catalog` is searched before any other schema (or
 * the operator is qualified with it) and the operand types select one of the
 * catalog operators below; every other combination depends on the
 * operator's declaration. An invalid operand makes the result invalid, a
 * dependent operand makes it depend on the same inputs. An operand of the
 * pseudo-type `unknown` (a string constant or NULL) takes the type of the
 * other operand. The table: the six comparisons are `boolean` for operands
 * of one category among numbers, strings, date and timestamp types, and for
 * the self-comparable types of PG-OPERATOR-COMPARISON-001, rows and arrays;
 * `+ - * /` over numbers give the wider of two integer types, `numeric` with
 * an integer and `numeric`, `double precision` once a floating-point type
 * and another type meet, and the type itself for two equal types; `%` is
 * defined for integers and `numeric`; `^` for `double precision` and
 * `numeric`, integers reaching it as `double precision`; date/time
 * arithmetic follows the table of functions-datetime.html; `||` gives
 * `text` when a side is a string, the array type for arrays, and the type
 * itself for `bytea`, bit strings, `jsonb`, `tsvector` and `tsquery`; the
 * bitwise operators keep the integer or bit-string type; the JSON operators
 * follow functions-json.html; prefix `-` and `+` keep a number or interval
 * type, and `-` before a numeric constant is that negative constant, typed
 * by its value. Comparisons and arithmetic are strict: the result can be
 * NULL when an operand can. Termination: constant work.
 * Source: https://www.postgresql.org/docs/17/typeconv-oper.html, https://www.postgresql.org/docs/17/functions-comparison.html,
 * https://www.postgresql.org/docs/17/functions-math.html, https://www.postgresql.org/docs/17/functions-datetime.html,
 * https://www.postgresql.org/docs/17/functions-string.html, https://www.postgresql.org/docs/17/functions-json.html,
 * https://www.postgresql.org/docs/17/functions-array.html, https://www.postgresql.org/docs/17/functions-bitstring.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OperatorTyping
{
    /**
     * The catalog operators over exact operand types beyond comparison and numeric arithmetic: `operator left right` => result.
     */
    private const EXACT = [
        '+ date int4' => 'date', '+ int4 date' => 'date', '- date int4' => 'date', '- date date' => 'int4',
        '+ date interval' => 'timestamp', '+ interval date' => 'timestamp', '- date interval' => 'timestamp',
        '+ date time' => 'timestamp', '+ time date' => 'timestamp', '+ date timetz' => 'timestamptz', '+ timetz date' => 'timestamptz',
        '+ timestamp interval' => 'timestamp', '+ interval timestamp' => 'timestamp', '- timestamp interval' => 'timestamp', '- timestamp timestamp' => 'interval',
        '+ timestamptz interval' => 'timestamptz', '+ interval timestamptz' => 'timestamptz', '- timestamptz interval' => 'timestamptz',
        '- timestamptz timestamptz' => 'interval',
        '+ time interval' => 'time', '+ interval time' => 'time', '- time interval' => 'time', '- time time' => 'interval',
        '+ timetz interval' => 'timetz', '+ interval timetz' => 'timetz', '- timetz interval' => 'timetz',
        '+ interval interval' => 'interval', '- interval interval' => 'interval', '* interval float8' => 'interval', '* float8 interval' => 'interval',
        '/ interval float8' => 'interval',
        '|| text text' => 'text', '|| bytea bytea' => 'bytea', '|| varbit varbit' => 'varbit', '|| bit bit' => 'varbit', '|| jsonb jsonb' => 'jsonb',
        '|| tsvector tsvector' => 'tsvector', '|| tsquery tsquery' => 'tsquery', '&& tsquery tsquery' => 'tsquery',
        '-> json int4' => 'json', '-> json text' => 'json', '->> json int4' => 'text', '->> json text' => 'text',
        '-> jsonb int4' => 'jsonb', '-> jsonb text' => 'jsonb', '->> jsonb int4' => 'text', '->> jsonb text' => 'text',
        '- jsonb text' => 'jsonb', '- jsonb int4' => 'jsonb', '@> jsonb jsonb' => 'bool', '<@ jsonb jsonb' => 'bool', '? jsonb text' => 'bool',
        '@? jsonb jsonpath' => 'bool', '@@ jsonb jsonpath' => 'bool', '@@ tsvector tsquery' => 'bool', '@@ tsquery tsvector' => 'bool',
        '@@ text tsquery' => 'bool', '@@ text text' => 'bool',
        '~ text text' => 'bool', '~* text text' => 'bool', '!~ text text' => 'bool', '!~* text text' => 'bool',
        '~~ text text' => 'bool', '~~* text text' => 'bool', '!~~ text text' => 'bool', '!~~* text text' => 'bool',
        '~~ bpchar text' => 'bool', '!~~ bpchar text' => 'bool', '~~ name text' => 'bool', '!~~ name text' => 'bool',
        '~~ bytea bytea' => 'bool', '!~~ bytea bytea' => 'bool', '^@ text text' => 'bool',
        '& bit bit' => 'bit', '| bit bit' => 'bit', '# bit bit' => 'bit', '<< bit int4' => 'bit', '>> bit int4' => 'bit',
        '<< inet inet' => 'bool', '<<= inet inet' => 'bool', '>> inet inet' => 'bool', '>>= inet inet' => 'bool', '&& inet inet' => 'bool',
    ];

    /**
     * The comparison operators.
     */
    private const COMPARISONS = ['=', '<>', '<', '>', '<=', '>='];

    /**
     * Types a binary operator application and derives that it can be NULL when an operand can.
     */
    public function binary(AnalysisContext $context, OperatorName $operator, ScalarFact $left, ScalarFact $right): ScalarFact
    {
        return new ScalarFact($this->named($context, $operator, $left->type, $right->type), (new OperandChecks())->nullability([$left, $right]));
    }

    /**
     * Types an application of a binary operator given by name or as an operator name.
     */
    public function named(AnalysisContext $context, OperatorName|string $operator, TypeFact $left, TypeFact $right): TypeFact
    {
        $operator = is_string($operator) ? new OperatorName(new Name($operator)) : $operator;
        foreach ([$left, $right] as $operand) {
            if ($operand instanceof Invalid || $operand instanceof Dependent) {
                return $operand;
            }
        }
        if (!$this->visible($context, $operator->name->value, $operator)) {
            return $this->undeclared($operator->name->value);
        }
        $result = $this->structured($operator->name->value, $left, $right) ?? $this->catalog($operator->name->value, $left, $right);

        return $result === null ? $this->undeclared($operator->name->value) : new Known($result);
    }

    /**
     * Types an operator over rows and arrays.
     */
    public function structured(string $operator, TypeFact $left, TypeFact $right): Builtin|ArrayOf|null
    {
        $a = $left instanceof Known ? $left->descriptor : null;
        $b = $right instanceof Known ? $right->descriptor : null;
        if ($a instanceof Composite || $b instanceof Composite) {
            $rows = ($a instanceof Composite || $left instanceof NullOnly) && ($b instanceof Composite || $right instanceof NullOnly);

            return $rows && in_array($operator, self::COMPARISONS, true) ? Builtin::Bool : null;
        }

        return $a === null || $b === null ? null : $this->arrays($operator, $a, $b);
    }

    /**
     * Types an operator over arrays: comparison and containment of equal array types, and `||` with arrays or elements.
     */
    public function arrays(string $operator, TypeDescriptor $left, TypeDescriptor $right): Builtin|ArrayOf|null
    {
        if ($left instanceof ArrayOf && $right instanceof ArrayOf && $left->name() === $right->name()) {
            if (in_array($operator, [...self::COMPARISONS, '@>', '<@', '&&'], true)) {
                return Builtin::Bool;
            }

            return $operator === '||' ? $left : null;
        }
        if ($operator !== '||') {
            return null;
        }
        if ($left instanceof ArrayOf && $left->element->name() === $right->name()) {
            return $left;
        }

        return $right instanceof ArrayOf && $right->element->name() === $left->name() ? $right : null;
    }

    /**
     * Types an operator over catalog base types; null when no catalog operator applies.
     */
    public function catalog(string $operator, TypeFact $left, TypeFact $right): ?Builtin
    {
        $categories = new Categories();
        $a = $categories->builtin($left);
        $b = $categories->builtin($right);
        if ($a === null || $b === null) {
            return null;
        }
        if ($a === Builtin::Unknown && $b === Builtin::Unknown) {
            return in_array($operator, self::COMPARISONS, true) ? Builtin::Bool : ($operator === '||' ? Builtin::Text : null);
        }
        if ($operator === '||' && ($a === Builtin::Unknown || $b === Builtin::Unknown)) {
            return Builtin::Text;
        }
        $a = $a === Builtin::Unknown ? $b : $a;
        $b = $b === Builtin::Unknown ? $a : $b;
        if (in_array($operator, self::COMPARISONS, true)) {
            return $this->comparable($a, $b) ? Builtin::Bool : null;
        }
        $exact = self::EXACT[$operator . ' ' . $a->value . ' ' . $b->value] ?? null;
        if ($exact !== null) {
            return Builtin::from($exact);
        }

        $arithmetic = new Arithmetic();

        return $arithmetic->arithmetic($operator, $a, $b) ?? $arithmetic->concatenation($operator, $a, $b) ?? $arithmetic->bitwise($operator, $a, $b);
    }

    /**
     * Tells whether `pg_catalog` compares two types, directly or after implicit conversion within their category.
     */
    public function comparable(Builtin $left, Builtin $right): bool
    {
        $categories = new Categories();
        if ((new ComparisonTyping())->exact($left, $right)) {
            return true;
        }
        if (($categories->numeric($left) && $categories->numeric($right)) || ($categories->textual($left) && $categories->textual($right))) {
            return true;
        }
        $times = [Builtin::Date, Builtin::Timestamp, Builtin::Timestamptz];

        return (in_array($left, $times, true) && in_array($right, $times, true))
            || ($left === $right && in_array($categories->category($left), ['B', 'N', 'S', 'D', 'T', 'V', 'I', 'R'], true));
    }

    /**
     * Combines the types of two boolean conditions that make one test.
     */
    public function both(TypeFact $first, TypeFact $second): TypeFact
    {
        foreach ([$first, $second] as $type) {
            if ($type instanceof Invalid) {
                return $type;
            }
        }
        if ($first instanceof Dependent && $second instanceof Dependent) {
            return new Dependent((new Unification())->distinct([...$first->missing, ...$second->missing]));
        }
        if ($first instanceof Dependent) {
            return $first;
        }

        return $second instanceof Dependent ? $second : new Known(Builtin::Bool);
    }

    /**
     * Tells whether a routine or operator name resolves in `pg_catalog` before any other schema.
     */
    public function visible(AnalysisContext $context, string $name, ?OperatorName $operator = null): bool
    {
        if ($operator !== null && $operator->qualifiers !== []) {
            return count($operator->qualifiers) === 1 && $operator->qualifiers[0]->value === 'pg_catalog';
        }
        foreach ($context->searchPath as $schema) {
            if ($schema->value !== 'pg_temp') {
                return $schema->value === 'pg_catalog';
            }
        }

        return false;
    }

    /**
     * Answers the fact of an operator or routine that only a declaration can type.
     */
    public function undeclared(string $name): Dependent
    {
        return new Dependent([new UndeclaredRoutine(new QualifiedName(new Name($name)))]);
    }
}
