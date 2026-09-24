<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Transfer;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\Maintenance\MaintenanceCommands;
use SqlSemantics\Binding\Statement\Utility\StringConstants;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Loading\Copy;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL COPY of a table from or to an endpoint, and of a query to an endpoint.
 * @visibility SqlSemantics
 */
final class CopyCommands
{
    /**
     * PROGRAM with STDIN or STDOUT, WHERE with COPY TO, and queries that return no rows are rejected as the server does.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Copy\CopyFromStatement|Copy\CopyToStatement|Copy\CopyQueryStatement
    {
        $endpoint = self::endpoint($source);
        $options = CopySettings::options(CopyOptionList::read($source, $context->tables->identifiers), $source);
        $query = Tree::child($source, ['PreparableStmt']);
        try {
            if ($query !== null) {
                return new Copy\CopyQueryStatement($origin, self::query($query, $context), $endpoint, $options);
            }
            $name = Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('COPY requires a table or a query.');
            $table = MaintenanceCommands::table($origin, $name, $context);
            $columnList = Tree::child($source, ['opt_column_list']);
            $columns = $columnList === null ? [] : CopyOptionList::names($columnList, $context->tables->identifiers);
            $where = Tree::child($source, ['where_clause']);
            if (strtoupper(Tree::child($source, ['copy_from'])?->tokens()[0]->text ?? '') === 'TO') {
                return $where !== null && $where->tokens() !== [] ? throw new InvalidSql(InputViolation::CopyOption, $where) : new Copy\CopyToStatement($origin, $table, $columns, $endpoint, $options);
            }
            $condition = $where === null ? null : Tree::child($where, ['a_expr']);
            $filter = $condition === null ? null : (new ExpressionBinder())->bind($condition, new Scope($context->tables->identifiers, [$table], queries: $context));
            return new Copy\CopyFromStatement($origin, $table, $columns, $endpoint, $options, $filter);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CopyOption, $source, $error);
        }
    }

    /**
     * Reads a server file, a server program or the client connection.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function endpoint(Node $source): Copy\Endpoint\CopyEndpoint
    {
        $file = Tree::child($source, ['copy_file_name']) ?? throw new UnclassifiedSql('COPY requires a file, program or client endpoint.');
        $program = (Tree::child($source, ['opt_program'])?->tokens() ?? []) !== [];
        $constant = Tree::child($file, ['Sconst']);
        if ($constant === null) {
            return $program ? throw new InvalidSql(InputViolation::CopyOption, $file) : new Copy\Endpoint\CopyClient();
        }
        $literal = StringConstants::literal($constant);
        return $program ? new Copy\Endpoint\CopyProgram($literal) : new Copy\Endpoint\CopyFile($literal);
    }

    /**
     * Binds the copied query; SELECT INTO and data-modifying statements without RETURNING are rejected.
     * @throws InvalidSql
     */
    public static function query(Node $query, QueryContext $context): \SqlSemantics\Model\BoundStatement&ResultStatement
    {
        foreach (Tree::outer($query, ['into_clause']) as $into) {
            if ($into->tokens() !== []) {
                throw new InvalidSql(InputViolation::CopyOption, $into);
            }
        }
        $statement = Tree::child($query, ['SelectStmt', 'InsertStmt', 'UpdateStmt', 'DeleteStmt', 'MergeStmt']) ?? $query;
        $bound = (new StatementBinder($context->tables))->node($statement, $statement, $context);
        return $bound instanceof ResultStatement && $bound->resultColumns() !== [] ? $bound : throw new InvalidSql(InputViolation::CopyOption, $query);
    }
}
