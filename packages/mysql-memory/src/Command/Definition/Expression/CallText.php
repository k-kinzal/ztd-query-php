<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use SqlSemantics\Platform\MySql\Statement\Call\CharCall;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Scalar;

/**
 * Writes the function calls of an expression of a table definition as the server stores them: the calls, casts and temporal arithmetic.
 *
 * A function is written in lower case under the name the server keeps for it: SUBSTRING and MID
 * are substr, UCASE upper, LCASE lower, POWER pow, CHARACTER_LENGTH char_length, ISNULL() a null
 * test; DATE(), TIME() and TIMESTAMP() of one argument are casts; DATE_ADD, ADDDATE, DATE_SUB and
 * SUBDATE are additions of an interval; CAST to CHAR names the character set; a JSON path
 * operator is JSON_EXTRACT (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility MySqlMemory
 */
final class CallText
{
    /**
     * The name the server keeps for a function written under another, by lowercase name.
     *
     * @var array<string, string>
     */
    public const NAMES = ['substring' => 'substr', 'mid' => 'substr', 'ucase' => 'upper', 'lcase' => 'lower', 'power' => 'pow', 'character_length' => 'char_length', 'schema' => 'database', 'session_user' => 'user', 'system_user' => 'user', 'current_user' => 'current_user'];

    /**
     * @param ItemText $items The writer of the expressions the calls are in
     */
    public function __construct(public readonly ItemText $items)
    {
    }

    /**
     * Writes a call, a cast or temporal arithmetic, or answers null for an expression the writer does not know.
     */
    public function scalar(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof FunctionCall => $this->function($scalar),
            $scalar instanceof KeywordCall => $this->keyword($scalar),
            $scalar instanceof ClockCall => strtolower($scalar->clock->value) . '(' . ($scalar->precision->text ?? '') . ')',
            $scalar instanceof Cast => $this->items->wrap($scalar->operand, 'cast(', ' as ' . $this->target($scalar->target) . ')'),
            $scalar instanceof BinaryCast => $this->items->wrap($scalar->operand, 'cast(', ' as char charset binary)'),
            $scalar instanceof CharsetConversion => $this->items->wrap($scalar->operand, 'convert(', ' using ' . strtolower($scalar->charset->name->value ?? 'binary') . ')'),
            $scalar instanceof JsonExtraction => $this->json($scalar),
            $scalar instanceof Trim => $this->trim($scalar),
            $scalar instanceof Extract => $this->items->wrap($scalar->source, 'extract(' . strtolower($scalar->unit->value) . ' from ', ')'),
            $scalar instanceof Position => $this->call('locate', [$scalar->substring, $scalar->string]),
            $scalar instanceof CharCall => $this->items->joined(array_map($this->items->scalar(...), $scalar->arguments), 'char(', ',', $scalar->charset === null ? ')' : ' using ' . strtolower($scalar->charset->name->value ?? 'binary') . ')'),
            $scalar instanceof DefaultOfColumn => $this->call('default', [$scalar->column]),
            default => $this->arithmetic($scalar),
        };
    }

    /**
     * Writes temporal arithmetic, an interval added to or subtracted from a date, or answers null for another expression.
     */
    public function arithmetic(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof IntervalArithmetic => $this->interval($scalar->operand, $scalar->interval->quantity, $scalar->interval->unit, $scalar->subtract),
            $scalar instanceof IntervalAddition => $this->interval($scalar->operand, $scalar->interval->quantity, $scalar->interval->unit, false),
            $scalar instanceof DateArithmetic => $this->interval($scalar->date, $scalar->quantity, $scalar->unit, $scalar->subtract),
            default => null,
        };
    }

    /**
     * Writes a call of a function by name.
     *
     * @param list<Scalar> $arguments
     */
    public function call(string $name, array $arguments): ?string
    {
        return $this->items->joined(array_map($this->items->scalar(...), $arguments), $name . '(', ',', ')');
    }

    /**
     * Writes a call of a function named by an identifier.
     */
    public function function(FunctionCall $call): ?string
    {
        $arguments = array_map(static fn ($argument): Scalar => $argument->expression, $call->arguments);
        if ($call->schema !== null) {
            return $this->call('`' . $call->schema->value . '`.`' . $call->name->value . '`', $arguments);
        }
        $name = strtolower($call->name->value);
        if ($name === 'isnull' && count($arguments) === 1) {
            return $this->items->wrap($arguments[0], '(', ' is null)');
        }
        if (in_array($name, ['adddate', 'subdate'], true) && count($arguments) === 2) {
            return $this->interval($arguments[0], $arguments[1], IntervalUnit::Day, $name === 'subdate');
        }

        return $this->call(self::NAMES[$name] ?? $name, $arguments);
    }

    /**
     * Writes a function named by a keyword: DATE(), TIME() and TIMESTAMP() of one argument as casts, MOD as %, and the others in lower case.
     */
    public function keyword(KeywordCall $call): ?string
    {
        $arguments = $call->arguments;
        $single = count($arguments) === 1;

        return match (true) {
            $call->function === KeywordFunction::Date && $single => $this->items->wrap($arguments[0], 'cast(', ' as date)'),
            $call->function === KeywordFunction::Time && $single => $this->items->wrap($arguments[0], 'cast(', ' as time)'),
            $call->function === KeywordFunction::Timestamp && $single => $this->items->wrap($arguments[0], 'cast(', ' as datetime)'),
            $call->function === KeywordFunction::Mod && count($arguments) === 2 => $this->items->binary($arguments[0], '%', $arguments[1]),
            ($call->function === KeywordFunction::AddDate || $call->function === KeywordFunction::SubDate) && count($arguments) === 2 => $this->interval($arguments[0], $arguments[1], IntervalUnit::Day, $call->function === KeywordFunction::SubDate),
            default => $this->call(self::NAMES[strtolower($call->function->value)] ?? strtolower($call->function->value), $arguments),
        };
    }

    /**
     * Writes the type a cast converts to; CHAR names its character set, the connection's when none is written.
     */
    public function target(CastTarget $target): string
    {
        $length = $target->length === null ? '' : '(' . $target->length . ($target->scale === null ? '' : ',' . $target->scale) . ')';
        if ($target->kind === CastKind::Char || $target->kind === CastKind::NationalChar) {
            $charset = $target->charset?->binary() === true ? 'binary' : strtolower($target->charset->charset->value ?? ($target->kind === CastKind::NationalChar ? 'utf8mb3' : $this->items->charset));

            return 'char' . $length . ' charset ' . $charset;
        }
        if ($target->kind === CastKind::Binary) {
            return 'char' . $length . ' charset binary';
        }

        return strtolower($target->kind->value) . $length;
    }

    /**
     * Writes the addition or subtraction of an interval.
     */
    public function interval(Scalar $operand, Scalar $quantity, IntervalUnit $unit, bool $subtract): ?string
    {
        return $this->items->joined([$this->items->scalar($operand), $this->items->scalar($quantity)], '(', $subtract ? ' - interval ' : ' + interval ', ' ' . strtolower($unit->value) . ')');
    }

    /**
     * Writes a JSON path operator as JSON_EXTRACT, and ->> as JSON_UNQUOTE of it.
     */
    public function json(JsonExtraction $extraction): ?string
    {
        $text = $this->items->wrap($extraction->column, 'json_extract(', ',' . $this->items->literal(new StringLiteral([$extraction->path->value])) . ')');

        return $text === null || !$extraction->unquote ? $text : 'json_unquote(' . $text . ')';
    }

    /**
     * Writes TRIM with its side and the string it removes.
     */
    public function trim(Trim $trim): ?string
    {
        if ($trim->side === null && $trim->removed === null) {
            return $this->call('trim', [$trim->subject]);
        }
        $removed = $trim->removed === null ? '' : $this->items->scalar($trim->removed);

        return $removed === null ? null : $this->items->wrap($trim->subject, 'trim(' . strtolower(($trim->side->value ?? 'BOTH')) . ' ' . ($removed === '' ? '' : $removed . ' ') . 'from ', ')');
    }
}
