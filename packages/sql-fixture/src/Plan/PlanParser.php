<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Reads the relation syntax of DBML into a plan.
 *
 *     order.id < order_detail.order_id
 *     order_detail.order_id > order.id
 *     order.id - order_shipping.order_id
 *     order.(shop_id, no) < order_detail.(shop_id, order_no)
 *     order.id < [order_detail.order_id, shipment.order_id]
 *     order_detail.order_id >? order.id
 *
 * Relations are separated by a comma or a newline. A plan naming a single
 * table and no relation is just that table name, so every table name is
 * already a valid plan.
 */
final class PlanParser
{
    private const IDENTIFIER = '/\G(?:`([^`]+)`|"([^"]+)"|([A-Za-z_][A-Za-z0-9_$]*))/';

    private string $source = '';
    private int $offset = 0;

    /**
     * @throws PlanSyntaxException
     */
    public function parse(string $plan): FixturePlan
    {
        if (str_contains($plan, '<>')) {
            throw PlanSyntaxException::manyToManyUnsupported($plan);
        }

        $statements = $this->split($plan);
        if ($statements === []) {
            throw PlanSyntaxException::emptyPlan();
        }

        $parts = [];

        foreach ($statements as $statement) {
            $this->source = $statement;
            $this->offset = 0;

            $parsed = $this->parseStatement();
            if (is_string($parsed)) {
                $parts[] = $parsed;
                continue;
            }

            $parts = [...$parts, ...$parsed];
        }

        return new FixturePlan(...$parts);
    }

    /**
     * Split on commas and newlines that are not inside brackets.
     *
     * @return array<int, string>
     */
    private function split(string $plan): array
    {
        $statements = [];
        $current = '';
        $depth = 0;

        foreach (str_split($plan) as $character) {
            if ($character === '[' || $character === '(') {
                $depth++;
            } elseif ($character === ']' || $character === ')') {
                $depth--;
            }

            if ($depth < 0) {
                throw PlanSyntaxException::unbalancedBrackets($plan);
            }

            if ($depth === 0 && ($character === ',' || $character === "\n" || $character === ';')) {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $character;
        }

        $statements[] = $current;

        return array_filter(
            array_map('trim', $statements),
            static fn (string $statement): bool => $statement !== ''
        );
    }

    /**
     * A statement is either a bare table name or one relation, which expands
     * to several when its target is a group.
     *
     * @return list<Relation>|string
     */
    private function parseStatement(): array|string
    {
        if (!str_contains($this->source, '.')) {
            $table = $this->readIdentifier('a table name');
            $this->expectEnd();

            return $table;
        }

        $left = $this->readEndpoint();
        $this->skipWhitespace();

        $leftOptional = $this->readOptionalMarker();
        $kind = $this->readOperator();
        $rightOptional = $this->readOptionalMarker();

        $this->skipWhitespace();
        $targets = $this->readTargets();
        $this->expectEnd();

        return $this->buildRelations($left, $kind, $targets, $leftOptional, $rightOptional);
    }

    /**
     * A grouped target expands to one relation per endpoint, so grouping is
     * only ever a shorthand for repeating the left end.
     *
     * @param list<ColumnRef> $targets
     * @return list<Relation>
     */
    private function buildRelations(
        ColumnRef $left,
        RelationKind $kind,
        array $targets,
        bool $leftOptional,
        bool $rightOptional,
    ): array {
        return array_map(
            static fn (ColumnRef $target): Relation => new Relation(
                $left,
                $kind,
                $target,
                $leftOptional,
                $rightOptional
            ),
            $targets
        );
    }

    /**
     * @return list<ColumnRef>
     */
    private function readTargets(): array
    {
        if ($this->peek() !== '[') {
            return [$this->readEndpoint()];
        }

        $this->offset++;
        $targets = [];

        while (true) {
            $this->skipWhitespace();
            $targets[] = $this->readEndpoint();
            $this->skipWhitespace();

            $character = $this->peek();
            if ($character === ',') {
                $this->offset++;
                continue;
            }

            if ($character === ']') {
                $this->offset++;
                return $targets;
            }

            throw PlanSyntaxException::unexpected($this->source, $this->offset, "',' or ']'");
        }
    }

    private function readEndpoint(): ColumnRef
    {
        $table = $this->readIdentifier('a table name');

        if ($this->peek() !== '.') {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, "'.' after the table name");
        }
        $this->offset++;

        if ($this->peek() !== '(') {
            return new ColumnRef($table, [$this->readIdentifier('a column name')]);
        }

        $this->offset++;
        $columns = [];

        while (true) {
            $this->skipWhitespace();
            $columns[] = $this->readIdentifier('a column name');
            $this->skipWhitespace();

            $character = $this->peek();
            if ($character === ',') {
                $this->offset++;
                continue;
            }

            if ($character === ')') {
                $this->offset++;
                return new ColumnRef($table, $columns);
            }

            throw PlanSyntaxException::unexpected($this->source, $this->offset, "',' or ')'");
        }
    }

    private function readOperator(): RelationKind
    {
        $character = $this->peek();
        $kind = $character === null ? null : RelationKind::tryFrom($character);

        if ($kind === null) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, "one of '<', '>' or '-'");
        }

        $this->offset++;

        return $kind;
    }

    private function readOptionalMarker(): bool
    {
        if ($this->peek() !== '?') {
            return false;
        }

        $this->offset++;

        return true;
    }

    private function readIdentifier(string $expected): string
    {
        if (preg_match(self::IDENTIFIER, $this->source, $matches, 0, $this->offset) !== 1) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, $expected);
        }

        $this->offset += strlen($matches[0]);

        foreach ([1, 2, 3] as $group) {
            if (isset($matches[$group]) && $matches[$group] !== '') {
                return $matches[$group];
            }
        }

        throw PlanSyntaxException::unexpected($this->source, $this->offset, $expected);
    }

    private function skipWhitespace(): void
    {
        while (($character = $this->peek()) !== null && trim($character) === '') {
            $this->offset++;
        }
    }

    private function expectEnd(): void
    {
        if ($this->offset < strlen($this->source)) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, 'the end of the relation');
        }
    }

    private function peek(): ?string
    {
        return $this->source[$this->offset] ?? null;
    }
}
