<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Model\Definition\Routine\Body;
use SqlSemantics\Model\Definition\Routine\Body\Declaration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the structure of a complete stored program body: labels, cursor and condition names, and RETURN.
 * @visibility SqlSemantics
 */
final class ProgramStructure
{
    /**
     * LEAVE and ITERATE target enclosing labels of their handler scope, labels are not reused while enclosed, OPEN, FETCH, CLOSE
     * and handlers name declared cursors and conditions, and RETURN appears only in, and at least once in, a function.
     * @throws InvalidStructure
     */
    public static function check(Body\ProgramStatement $body, ProgramKind $kind): void
    {
        $returns = self::walk($body, $kind, [], []);
        if ($kind === ProgramKind::Function && $returns === 0) {
            throw new InvalidStructure('A stored function requires a RETURN statement.');
        }
    }

    /**
     * Walks one statement with the visible labels (name => loop) and declared cursor and condition names; returns the RETURN count.
     * @param array<string, bool> $labels
     * @param array<string, true> $names
     * @throws InvalidStructure
     */
    public static function walk(Body\ProgramStatement $statement, ProgramKind $kind, array $labels, array $names): int
    {
        $labels = self::labels($statement, $labels);
        self::references($statement, $kind, $labels, $names);
        $returns = $statement instanceof Body\ReturnStatement ? 1 : 0;
        if ($statement instanceof Body\BlockStatement) {
            foreach ($statement->declarations as $declaration) {
                $names = [...$names, ...array_fill_keys(Declaration\DeclarationOrder::names($declaration), true)];
                $returns += $declaration instanceof Declaration\HandlerDeclaration ? self::handler($declaration, $kind, $names) : 0;
            }
        }
        foreach (self::children($statement) as $child) {
            $returns += self::walk($child, $kind, $labels, $names);
        }
        return $returns;
    }

    /**
     * Adds a block or loop label, which no enclosing block or loop of the same handler scope may use.
     * @param array<string, bool> $labels
     * @return array<string, bool>
     * @throws InvalidStructure
     */
    public static function labels(Body\ProgramStatement $statement, array $labels): array
    {
        $label = $statement instanceof Body\BlockStatement || $statement instanceof Body\LoopStatement || $statement instanceof Body\WhileStatement || $statement instanceof Body\RepeatStatement ? $statement->label : null;
        if ($label === null) {
            return $labels;
        }
        if (isset($labels[strtolower($label)])) {
            throw new InvalidStructure('A label is not reused inside the block or loop it names.');
        }
        return [...$labels, strtolower($label) => !$statement instanceof Body\BlockStatement];
    }

    /**
     * Checks the labels, cursor and RETURN a single statement refers to.
     * @param array<string, bool> $labels
     * @param array<string, true> $names
     * @throws InvalidStructure
     */
    public static function references(Body\ProgramStatement $statement, ProgramKind $kind, array $labels, array $names): void
    {
        if (($statement instanceof Body\LeaveStatement && !isset($labels[strtolower($statement->label)])) || ($statement instanceof Body\IterateStatement && ($labels[strtolower($statement->label)] ?? false) !== true)) {
            throw new InvalidStructure('LEAVE names an enclosing label and ITERATE an enclosing loop.');
        }
        if ($statement instanceof Body\ReturnStatement && $kind !== ProgramKind::Function) {
            throw new InvalidStructure('RETURN is only allowed in a stored function.');
        }
        $cursor = $statement instanceof Body\Cursor\CursorOpenStatement || $statement instanceof Body\Cursor\CursorFetchStatement || $statement instanceof Body\Cursor\CursorCloseStatement ? $statement->cursor : null;
        if ($cursor !== null && !isset($names['cursor:' . strtolower($cursor)])) {
            throw new InvalidStructure('OPEN, FETCH and CLOSE name a declared cursor.');
        }
    }

    /**
     * Walks a handler, which names declared conditions and sees no label outside itself; returns its RETURN count.
     * @param array<string, true> $names
     * @throws InvalidStructure
     */
    public static function handler(Declaration\HandlerDeclaration $handler, ProgramKind $kind, array $names): int
    {
        foreach ($handler->conditions as $condition) {
            if ($condition instanceof Declaration\NamedCondition && !isset($names['condition:' . strtolower($condition->name)])) {
                throw new InvalidStructure('A handler names a declared condition.');
            }
        }
        return self::walk($handler->statement, $kind, [], $names);
    }

    /**
     * Returns the statements nested directly in a compound or flow-control statement.
     * @return list<Body\ProgramStatement>
     */
    public static function children(Body\ProgramStatement $statement): array
    {
        return match (true) {
            $statement instanceof Body\BlockStatement, $statement instanceof Body\LoopStatement, $statement instanceof Body\WhileStatement, $statement instanceof Body\RepeatStatement => $statement->statements,
            $statement instanceof Body\IfStatement => [...array_merge(...array_map(static fn (Body\ConditionalBranch $branch): array => $branch->statements, $statement->branches)), ...$statement->otherwise],
            $statement instanceof Body\SimpleCaseStatement, $statement instanceof Body\SearchedCaseStatement => [...array_merge(...array_map(static fn (Body\ConditionalBranch $branch): array => $branch->statements, $statement->whens)), ...$statement->otherwise],
            default => [],
        };
    }
}
