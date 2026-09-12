<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Parsing;

use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;

/**
 * Reads endpoints and optional relation groups from one statement.
 *
 * @visibility root
 */
final class RelationReader
{
    /**
     * Reads from the supplied statement cursor.
     */
    public function __construct(private readonly RelationCursor $cursor)
    {
    }

    /**
     * A statement is either a bare table name or one relation, which expands
     * to several when its target is a group.
     *
     * @return list<Relation>|string
     */
    public function parseStatement(): array|string
    {
        if (!str_contains($this->cursor->source, '.')) {
            $table = $this->cursor->readIdentifier('a table name');
            $this->cursor->expectEnd();

            return $table;
        }

        $left = $this->readEndpoint();
        $this->cursor->skipWhitespace();

        $leftOptional = $this->cursor->readOptionalMarker();
        $kind = $this->cursor->readOperator();
        $rightOptional = $this->cursor->readOptionalMarker();

        $this->cursor->skipWhitespace();
        $targets = $this->readTargets();
        $this->cursor->expectEnd();

        return $this->buildRelations($left, $kind, $targets, $leftOptional, $rightOptional);
    }

    /**
     * @return list<ColumnRef>
     * @throws PlanSyntaxException
     */
    public function readTargets(): array
    {
        if ($this->cursor->peek() !== '[') {
            return [$this->readEndpoint()];
        }

        $this->cursor->offset++;
        $targets = [];

        while (true) {
            $this->cursor->skipWhitespace();
            $targets[] = $this->readEndpoint();
            $this->cursor->skipWhitespace();

            $character = $this->cursor->peek();
            if ($character === ',') {
                $this->cursor->offset++;
                continue;
            }

            if ($character === ']') {
                $this->cursor->offset++;
                return $targets;
            }

            throw PlanSyntaxException::unexpected($this->cursor->source, $this->cursor->offset, "',' or ']'");
        }
    }

    /**
     * Reads endpoint.
     * @throws PlanSyntaxException
     */
    public function readEndpoint(): ColumnRef
    {
        $table = $this->cursor->readIdentifier('a table name');

        if ($this->cursor->peek() !== '.') {
            throw PlanSyntaxException::unexpected($this->cursor->source, $this->cursor->offset, "'.' after the table name");
        }
        $this->cursor->offset++;

        if ($this->cursor->peek() !== '(') {
            return new ColumnRef($table, [$this->cursor->readIdentifier('a column name')]);
        }

        $this->cursor->offset++;
        $columns = [];

        while (true) {
            $this->cursor->skipWhitespace();
            $columns[] = $this->cursor->readIdentifier('a column name');
            $this->cursor->skipWhitespace();

            $character = $this->cursor->peek();
            if ($character === ',') {
                $this->cursor->offset++;
                continue;
            }

            if ($character === ')') {
                $this->cursor->offset++;
                return new ColumnRef($table, $columns);
            }

            throw PlanSyntaxException::unexpected($this->cursor->source, $this->cursor->offset, "',' or ')'");
        }
    }

    /**
     * A grouped target expands to one relation per endpoint, so grouping is
     * only ever a shorthand for repeating the left end.
     *
     * @param list<ColumnRef> $targets
     * @return list<Relation>
     */
    public function buildRelations(
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
}
