<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Cursor;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor;
use SqlSemantics\Model\Statement\Cursor as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies PostgreSQL cursor declaration, movement, fetching, and closing.
 * @visibility SqlSemantics
 */
final class CursorBinder
{
    /**
     * Returns null only when the node belongs to another statement family.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($source->name === 'DeclareCursorStmt') {
            $query = Tree::child($source, ['SelectStmt']) ?? throw new UnclassifiedSql('A cursor declaration requires a query.');
            $name = self::name($source, $context);
            $options = strtoupper(Tree::text(Tree::child($source, ['cursor_options']) ?? new Node('empty', 0, [])));
            if (str_contains($options, 'NO SCROLL') && preg_match('/(?<!NO )SCROLL/', $options) === 1) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::CursorOptions, $source);
            }
            $scroll = str_contains($options, 'NO SCROLL') ? Cursor\Scrollability::ForwardOnly : (str_contains($options, 'SCROLL') ? Cursor\Scrollability::Scroll : Cursor\Scrollability::Default);
            $sensitivity = str_contains($options, 'INSENSITIVE') ? Cursor\Sensitivity::Insensitive : (str_contains($options, 'ASENSITIVE') ? Cursor\Sensitivity::Asensitive : Cursor\Sensitivity::Default);
            $hold = strtoupper(Tree::text(Tree::child($source, ['opt_hold']) ?? new Node('empty', 0, []))) === 'WITH HOLD';
            return new Statement\DeclareCursorStatement($origin, $name, $context->bind($query), $scroll, $sensitivity, str_contains($options, 'BINARY'), $hold);
        }
        if ($source->name === 'FetchStmt') {
            $args = Tree::child($source, ['fetch_args']) ?? throw new UnclassifiedSql('A cursor movement requires its operands.');
            $name = self::name($args, $context);
            return strtoupper($source->tokens()[0]->text) === 'FETCH' ? new Statement\FetchCursorStatement($origin, $name, self::movement($args)) : new Statement\MoveCursorStatement($origin, $name, self::movement($args));
        }
        if ($source->name === 'ClosePortalStmt') {
            return Tree::child($source, ['cursor_name']) === null ? new Statement\CloseAllCursorsStatement($origin) : new Statement\CloseCursorStatement($origin, self::name($source, $context));
        }
        return null;
    }

    /**
     * Reads the cursor name from its own grammar operand.
     * @throws UnclassifiedSql
     */
    public static function name(Node $source, QueryContext $context): string
    {
        $name = Tree::child($source, ['cursor_name']) ?? throw new UnclassifiedSql('A cursor operation requires a name.');
        return $context->tables->identifiers->parts($name)[0];
    }

    /**
     * Separates single-row positions from counted and unlimited scans.
     * @throws UnclassifiedSql
     */
    public static function movement(Node $source): Cursor\Movement
    {
        $parts = array_values(array_filter(Tree::significant($source), static fn ($part): bool => !$part instanceof Node || !in_array($part->name, ['cursor_name', 'opt_from_in', 'from_in'], true)));
        $words = array_map(static fn ($part): string => strtoupper(Tree::text($part)), $parts);
        $first = $words[0] ?? 'NEXT';
        $position = Cursor\RowPosition::tryFrom($first);
        if ($position !== null) {
            return $position;
        }
        $number = Tree::child($source, ['SignedIconst', 'Iconst']);
        $offset = $number === null ? null : new Cursor\IntegerOffset(str_replace(' ', '', Tree::text($number)));
        $origin = Cursor\OffsetOrigin::tryFrom($first);
        if ($origin !== null) {
            return new Cursor\PositionedRow($origin, $offset ?? throw new UnclassifiedSql('An absolute or relative cursor position requires an offset.'));
        }
        $direction = Cursor\ScanDirection::tryFrom($first) ?? Cursor\ScanDirection::Forward;
        if (in_array('ALL', $words, true)) {
            return new Cursor\RemainingRows($direction);
        }
        return $offset === null ? ($direction === Cursor\ScanDirection::Forward ? Cursor\RowPosition::Next : Cursor\RowPosition::Prior) : new Cursor\CountedRows($direction, $offset);
    }
}
