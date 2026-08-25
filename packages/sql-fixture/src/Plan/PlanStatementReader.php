<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Reads one statement of a plan.
 *
 * A statement is either a table named on its own or one relation between two
 * endpoints. A relation whose target is a group stands for one relation per
 * member of that group, so grouping is only ever shorthand for repeating the
 * left-hand end.
 */
final class PlanStatementReader
{
    /**
     * Reads the statement the cursor is at.
     *
     * A statement with no dot in it can only be a table name, which is what
     * lets a plan that names one table be written as just that name.
     *
     * @param PlanCursor $cursor Cursor over the statement
     *
     * @return list<Relation>|string The relations it declares, or the table it names
     *
     * @throws PlanSyntaxException When the statement is not written as a table name or a relation
     */
    public function read(PlanCursor $cursor): array|string
    {
        if (!str_contains($cursor->source(), '.')) {
            $table = $cursor->takeIdentifier('a table name');
            $cursor->expectEnd();

            return $table;
        }

        $left = $this->endpoint($cursor);
        $cursor->skipWhitespace();
        $leftOptional = $cursor->accept('?');
        $kind = $this->operator($cursor);
        $rightOptional = $cursor->accept('?');
        $cursor->skipWhitespace();
        $targets = $this->targets($cursor);
        $cursor->expectEnd();

        return array_map(
            static fn (ColumnRef $target): Relation => new Relation(
                $left,
                $kind,
                $target,
                $leftOptional,
                $rightOptional,
            ),
            $targets,
        );
    }

    /**
     * Reads the right-hand end, which may name one endpoint or a group of them.
     *
     * @param PlanCursor $cursor Cursor over the statement
     *
     * @return list<ColumnRef> The endpoints named
     *
     * @throws PlanSyntaxException When a group is not closed, or a member is not an endpoint
     */
    public function targets(PlanCursor $cursor): array
    {
        if (!$cursor->accept('[')) {
            return [$this->endpoint($cursor)];
        }

        $targets = [];
        while (true) {
            $cursor->skipWhitespace();
            $targets[] = $this->endpoint($cursor);
            $cursor->skipWhitespace();
            if ($cursor->accept(',')) {
                continue;
            }
            if ($cursor->accept(']')) {
                return $targets;
            }

            throw $cursor->unexpected("',' or ']'");
        }
    }

    /**
     * Reads one end of a relation, which names a table and one or more of its columns.
     *
     * @param PlanCursor $cursor Cursor over the statement
     *
     * @return ColumnRef The table and columns named
     *
     * @throws PlanSyntaxException When no table and column are written here
     */
    public function endpoint(PlanCursor $cursor): ColumnRef
    {
        $table = $cursor->takeIdentifier('a table name');
        if (!$cursor->accept('.')) {
            throw $cursor->unexpected("'.' after the table name");
        }
        if (!$cursor->accept('(')) {
            return new ColumnRef($table, [$cursor->takeIdentifier('a column name')]);
        }

        $columns = [];
        while (true) {
            $cursor->skipWhitespace();
            $columns[] = $cursor->takeIdentifier('a column name');
            $cursor->skipWhitespace();
            if ($cursor->accept(',')) {
                continue;
            }
            if ($cursor->accept(')')) {
                return new ColumnRef($table, $columns);
            }

            throw $cursor->unexpected("',' or ')'");
        }
    }

    /**
     * Reads which way the relation runs.
     *
     * @param PlanCursor $cursor Cursor over the statement
     *
     * @return RelationKind The kind the operator names
     *
     * @throws PlanSyntaxException When no relation operator is written here
     */
    public function operator(PlanCursor $cursor): RelationKind
    {
        $character = $cursor->peek();
        $kind = $character === null ? null : RelationKind::tryFrom($character);
        if ($kind === null) {
            throw $cursor->unexpected("one of '<', '>' or '-'");
        }
        $cursor->accept($character ?? '');

        return $kind;
    }
}
