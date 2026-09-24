<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Program;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Retrievals;
use SqlSemantics\Serialization\Statements;

/**
 * Writes the compound, flow-control and cursor statements of a stored program body.
 * @visibility SqlSemantics
 */
final class ProgramBodies
{
    /**
     * Writes one body statement without its terminating semicolon.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(Body\ProgramStatement $statement): Tree
    {
        return match (true) {
            $statement instanceof Body\BlockStatement => self::labeled($statement->label, [Build::keyword('BEGIN'), ...array_map(static fn ($declaration): Tree => self::terminated(ProgramDeclarations::write($declaration)), $statement->declarations), self::statements($statement->statements), Build::keyword('END')]),
            $statement instanceof Body\IfStatement => new Tree('if', [...self::branches('IF', 'ELSEIF', $statement->branches), ...self::otherwise($statement->otherwise), Build::keyword('END IF')]),
            $statement instanceof Body\SimpleCaseStatement => new Tree('case', [Build::keyword('CASE'), Expressions::write($statement->operand), ...self::branches('WHEN', 'WHEN', $statement->whens), ...self::otherwise($statement->otherwise), Build::keyword('END CASE')]),
            $statement instanceof Body\SearchedCaseStatement => new Tree('case', [Build::keyword('CASE'), ...self::branches('WHEN', 'WHEN', $statement->whens), ...self::otherwise($statement->otherwise), Build::keyword('END CASE')]),
            $statement instanceof Body\LoopStatement => self::labeled($statement->label, [Build::keyword('LOOP'), self::statements($statement->statements), Build::keyword('END LOOP')]),
            $statement instanceof Body\WhileStatement => self::labeled($statement->label, [Build::keyword('WHILE'), Expressions::write($statement->condition), Build::keyword('DO'), self::statements($statement->statements), Build::keyword('END WHILE')]),
            $statement instanceof Body\RepeatStatement => self::labeled($statement->label, [Build::keyword('REPEAT'), self::statements($statement->statements), Build::keyword('UNTIL'), Expressions::write($statement->until), Build::keyword('END REPEAT')]),
            $statement instanceof Body\LeaveStatement => new Tree('leave', [Build::keyword('LEAVE'), Build::identifier([$statement->label], Dialect::MySql)]),
            $statement instanceof Body\IterateStatement => new Tree('iterate', [Build::keyword('ITERATE'), Build::identifier([$statement->label], Dialect::MySql)]),
            $statement instanceof Body\ReturnStatement => new Tree('return', [Build::keyword('RETURN'), Expressions::write($statement->value)]),
            $statement instanceof Body\EmbeddedStatement => Statements::write($statement->statement),
            $statement instanceof Body\AssignmentStatement => ProgramDeclarations::assignments($statement),
            $statement instanceof Body\SelectIntoStatement => Retrievals::last($statement->query, new Tree('into', [Build::keyword('INTO'), Build::separated(array_map(Expressions::write(...), $statement->targets))])),
            $statement instanceof Body\Cursor\CursorOpenStatement => new Tree('open', [Build::keyword('OPEN'), Build::identifier([$statement->cursor], Dialect::MySql)]),
            $statement instanceof Body\Cursor\CursorCloseStatement => new Tree('close', [Build::keyword('CLOSE'), Build::identifier([$statement->cursor], Dialect::MySql)]),
            $statement instanceof Body\Cursor\CursorFetchStatement => new Tree('fetch', [Build::keyword('FETCH'), Build::identifier([$statement->cursor], Dialect::MySql), Build::keyword('INTO'), Build::separated(array_map(Expressions::write(...), $statement->targets))]),
            default => throw new \SqlSemantics\Model\Validation\InvalidStructure('Unclassified stored program statement.'),
        };
    }

    /**
     * Writes statements each followed by a semicolon.
     * @param list<Body\ProgramStatement> $statements
     */
    public static function statements(array $statements): Tree
    {
        return new Tree('statements', array_map(static fn (Body\ProgramStatement $statement): Tree => self::terminated(self::write($statement)), $statements));
    }

    /**
     * Appends the statement terminator.
     */
    public static function terminated(Tree $statement): Tree
    {
        return new Tree('terminated', [$statement, new Atom('punctuation', ';')]);
    }

    /**
     * Writes an optional begin label and the matching end label around a block or loop.
     * @param list<Tree> $parts
     */
    public static function labeled(?string $label, array $parts): Tree
    {
        if ($label === null) {
            return new Tree('compound', $parts);
        }
        $name = Build::identifier([$label], Dialect::MySql);
        return new Tree('compound', [$name, new Atom('punctuation', ':'), ...$parts, $name]);
    }

    /**
     * Writes guarded branches: the first with its keyword and the rest with the continuation keyword.
     * @param non-empty-list<Body\ConditionalBranch> $branches
     * @return list<Tree>
     */
    public static function branches(string $first, string $rest, array $branches): array
    {
        $parts = [];
        foreach ($branches as $index => $branch) {
            array_push($parts, Build::keyword($index === 0 ? $first : $rest), Expressions::write($branch->condition), Build::keyword('THEN'), self::statements($branch->statements));
        }
        return $parts;
    }

    /**
     * Writes ELSE and its statements when present.
     * @param list<Body\ProgramStatement> $statements
     * @return list<Tree>
     */
    public static function otherwise(array $statements): array
    {
        return $statements === [] ? [] : [Build::keyword('ELSE'), self::statements($statements)];
    }
}
