<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Composition;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Writer;

/**
 * Composes queries and expressions from templates the release reads, with every slot replaced.
 *
 * A template writes the form with bare names where operands and names go:
 * `slot0`, `slot1`, ... for operands and `slot_0`, `slot_1`, ... for names.
 * Each operand is placed at the position of its slot, parenthesized where it
 * binds more weakly than the position needs, and each name is spelled for
 * the role of its position. A database package supplies how its CAST writes
 * a type.
 *
 * @visibility SqlSemantics
 */
trait Templating
{
    private ?Templates $templates = null;

    /**
     * Spells a type as the target of the release's CAST.
     *
     * @throws CompositionException When CAST has no target of that type or cannot state one of its facts
     */
    abstract protected function castType(TypeDescriptor $type): string;

    /**
     * Answers the grammar symbol of an expression of this database, the role a composed expression occupies.
     */
    abstract protected function expressionSymbol(): string;

    /**
     * Answers the grammar symbol of the table names table() composes.
     */
    abstract protected function tableSymbol(): string;

    /**
     * `IS NULL`, or `IS NOT NULL` when negated.
     */
    public function isNull(Element $operand, bool $negated = false): Element
    {
        return $this->expression('slot0 IS ' . ($negated ? 'NOT ' : '') . 'NULL', [$operand]);
    }

    /**
     * `IN` a list of values, or `NOT IN` when negated.
     */
    public function in(Element $operand, array $values, bool $negated = false): Element
    {
        $operands = [$operand, ...$values];
        $slots = implode(', ', array_map(static fn (int $index): string => 'slot' . $index, array_slice(array_keys($operands), 1)));

        return $this->expression('slot0 ' . ($negated ? 'NOT ' : '') . 'IN (' . $slots . ')', $operands);
    }

    /**
     * A searched CASE: the result of the first condition that holds, else the default or NULL.
     */
    public function case(array $whens, ?Element $else = null): Element
    {
        if ($whens === []) {
            throw new CompositionException('A CASE needs at least one WHEN.');
        }
        $text = 'CASE';
        $operands = [];
        foreach ($whens as [$condition, $result]) {
            $text .= ' WHEN slot' . count($operands) . ' THEN slot' . (count($operands) + 1);
            array_push($operands, $condition, $result);
        }
        if ($else !== null) {
            $text .= ' ELSE slot' . count($operands);
            $operands[] = $else;
        }

        return $this->expression($text . ' END', $operands);
    }

    /**
     * A call of the function a name denotes: the name bare when its characters survive unquoted, quoted otherwise.
     *
     * A bare name the release reads as a keyword is the function the grammar
     * gives that keyword; quoting it would call another function, so a call
     * the grammar has no form for is an error.
     */
    public function call(string $name, array $arguments = []): Element
    {
        if ($name === '') {
            throw new CompositionException('A function name must not be empty.');
        }
        $spelled = $this->bare($name) ?? $this->quote($name);
        $marker = $this->slotMarker($spelled);
        $slots = '(' . implode(', ', array_map(static fn (int $index): string => $marker . $index, array_keys($arguments))) . ')';

        return $this->expression($spelled . $slots, $arguments, $marker);
    }

    /**
     * `CAST` of an operand to a type, spelled as the release's CAST names it.
     */
    public function cast(Element $operand, TypeDescriptor $type): Element
    {
        return $this->expression('CAST(slot0 AS ' . $this->castType($type) . ')', [$operand]);
    }

    /**
     * A SELECT of columns, each an expression with an optional alias, from an optional table and filtered by an optional condition.
     *
     * The table is a table name, such as one answered by table(); it is
     * written into the template as it is written, so the slots are named
     * with a word it does not contain.
     */
    public function select(array $columns, ?Element $from = null, ?Element $where = null): Element
    {
        if ($columns === []) {
            throw new CompositionException('A SELECT needs at least one column.');
        }
        if ($from !== null) {
            $this->expect($from, $this->tableSymbol(), 'The table of a SELECT');
        }
        $table = $from === null ? '' : Writer::render($from);
        $marker = $this->slotMarker($table);
        $items = [];
        $operands = [];
        $names = [];
        foreach ($columns as [$expression, $alias]) {
            $item = $marker . count($operands);
            $operands[] = $expression;
            if ($alias !== null) {
                $item .= ' AS ' . $marker . '_' . count($names);
                $names[] = $alias;
            }
            $items[] = $item;
        }
        $text = 'SELECT ' . implode(', ', $items) . ($table === '' ? '' : ' FROM ' . $table);
        if ($where !== null) {
            $text .= ' WHERE ' . $marker . count($operands);
            $operands[] = $where;
        }

        return $this->substitute($this->unwrap($this->templates()->command($text)), $marker, $operands, $names);
    }

    /**
     * Composes the expression a template writes as the column of a SELECT, with its slots replaced.
     *
     * @param list<Element> $operands The operand of each slot, by slot number
     *
     * @throws CompositionException When the release reads no such expression
     */
    protected function expression(string $text, array $operands, string $marker = 'slot'): Element
    {
        $value = $this->templates()->written($this->templates()->command('SELECT ' . $text), $text, $this->roleInterface($this->expressionSymbol()));
        if ($value === null) {
            throw new CompositionException('No expression ' . $text . ' in ' . $this->language->version);
        }

        return $this->substitute($value, $marker, $operands);
    }

    /**
     * Answers the letters slots begin with: `slot`, lengthened until the text written into a template around the slots does not contain it.
     */
    protected function slotMarker(string $text): string
    {
        $marker = 'slot';
        while (stripos($text, $marker) !== false) {
            $marker .= 'x';
        }

        return $marker;
    }

    /**
     * Replaces the slots of a template value: an operand at its position, parenthesized where it binds too weakly, and a name spelled for the role of its position.
     *
     * A slot is a leaf that writes the marker and a number, `slot0`, for an
     * operand, or the marker, an underscore, and a number, `slot_0`, for a
     * name.
     *
     * @param string $marker The letters that begin every slot
     * @param list<Element> $operands The operand of each operand slot, by its number
     * @param list<string> $names The name of each name slot, by its number
     *
     * @throws CompositionException When an operand cannot stand at its position
     */
    protected function substitute(Element $value, string $marker, array $operands, array $names = []): Element
    {
        $shape = $this->vocabulary->shape($value);
        if ($shape === null) {
            return $value;
        }
        $positions = array_keys(array_filter($shape['symbols'], $this->vocabulary->isRule(...)));
        $replacements = [];
        foreach ($value->children() as $index => $child) {
            $text = $child->children() === [] ? Writer::render($child) : '';
            $replacements[] = match (true) {
                preg_match('/^' . $marker . '(\d+)$/D', $text, $slot) === 1 => $this->placed($operands[(int) $slot[1]] ?? throw new CompositionException('No operand for the slot ' . $text), $shape['rule'], $shape['symbols'], $positions[$index]),
                preg_match('/^' . $marker . '_(\d+)$/D', $text, $slot) === 1 => $this->name($shape['symbols'][$positions[$index]], $names[(int) $slot[1]] ?? throw new CompositionException('No name for the slot ' . $text)),
                default => $this->substitute($child, $marker, $operands, $names),
            };
        }
        $index = 0;

        return $value->map(static function () use (&$index, $replacements): Element {
            return $replacements[$index++];
        });
    }

    /**
     * Places an operand at a symbol position of a form, parenthesized where it does not belong there.
     *
     * @param list<string> $symbols
     *
     * @throws CompositionException When the operand cannot stand at the position even parenthesized
     */
    protected function placed(Element $operand, string $rule, array $symbols, int $position): Element
    {
        $placed = $this->operand($operand, $rule, $symbols, $position);
        $role = $this->roleInterface($symbols[$position]);
        if (!$placed instanceof $role) {
            throw new CompositionException('An operand of ' . $rule . ' must be a ' . $symbols[$position] . ' of ' . $this->language->version . ', ' . $operand::class . ' given.');
        }

        return $placed;
    }

    /**
     * Requires a type to state no fact but the ones its CAST target writes.
     *
     * @param list<string> $facts The facts the target writes, by their field names
     *
     * @throws CompositionException When the type states another fact
     */
    protected function statesOnly(TypeDescriptor $type, array $facts): void
    {
        $stated = [
            'length' => $type->length !== null,
            'precision' => $type->precision !== null,
            'scale' => $type->scale !== null,
            'unsigned' => $type->unsigned,
            'zerofill' => $type->zerofill,
            'binaryCollation' => $type->binaryCollation,
            'characterSet' => $type->characterSet !== null,
            'members' => $type->members !== [],
            'arrayDimensions' => $type->arrayDimensions !== 0,
            'intervalFields' => $type->intervalFields !== null,
            'affinity' => $type->affinity !== null,
        ];
        foreach ($stated as $fact => $present) {
            if ($present && !in_array($fact, $facts, true)) {
                throw new CompositionException('A CAST to ' . $type->label() . ' in ' . $this->language->version . ' cannot state its ' . $fact . '.');
            }
        }
    }

    /**
     * Answers the templates of the language, read once for this builder.
     */
    protected function templates(): Templates
    {
        return $this->templates ??= new Templates($this->language);
    }
}
