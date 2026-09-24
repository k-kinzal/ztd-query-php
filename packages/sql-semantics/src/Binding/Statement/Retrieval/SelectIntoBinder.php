<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Retrieval;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Procedural\LoadClauses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;

/**
 * Binds the INTO clause of a SELECT statement: MySQL user variables, OUTFILE and DUMPFILE, and PostgreSQL's new table.
 * @visibility SqlSemantics
 */
final class SelectIntoBinder
{
    /**
     * Returns the SELECT ... INTO form, or null when the query has no INTO clause.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Node $statement, BoundQuery $query, QueryContext $context): ?BoundStatement
    {
        $clauses = IntoPlacement::clauses($statement);
        if ($clauses === []) {
            return null;
        }
        $dialect = $context->tables->identifiers->dialect;
        $into = $clauses[0];
        if (count($clauses) > 1 || !($dialect === Dialect::PostgreSql ? IntoPlacement::first($statement, $into) : IntoPlacement::last($statement, $into))) {
            throw new InvalidSql(InputViolation::SelectInto, $clauses[count($clauses) - 1]);
        }
        $origin = new Origin($context->ids->scope(), $source, $dialect);
        try {
            return $dialect === Dialect::PostgreSql ? self::table($origin, $into, $query, $context) : self::destination($origin, $into, $query, $context);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SelectInto, $into, $error);
        }
    }

    /**
     * Binds PostgreSQL `INTO [TEMPORARY | TEMP | LOCAL | GLOBAL | UNLOGGED] [TABLE] name`.
     * @throws InvalidStructure
     * @throws InvalidSql
     */
    public static function table(Origin $origin, Node $into, BoundQuery $query, QueryContext $context): Retrieval\SelectIntoTableStatement
    {
        $name = Tree::outer($into, ['qualified_name'])[0] ?? Tree::invalid($into, 'SELECT INTO table name');
        $target = Tree::child($into, ['OptTempTableName']) ?? $into;
        $first = Tree::significant($target)[0] ?? null;
        $word = $first instanceof \SqlParser\Lexer\Token ? strtoupper($first->text) : '';
        $persistence = match (true) {
            in_array($word, ['TEMPORARY', 'TEMP', 'LOCAL', 'GLOBAL'], true) => Persistence::Temporary,
            $word === 'UNLOGGED' => Persistence::Unlogged,
            default => Persistence::Permanent,
        };
        $parts = $context->tables->identifiers->parts($name);
        if ($persistence === Persistence::Temporary && count($parts) > 1 && preg_match('/^pg_temp(_\d+)?$/D', $parts[count($parts) - 2]) !== 1) {
            throw new InvalidSql(InputViolation::TemporaryTableSchema, $name);
        }
        return new Retrieval\SelectIntoTableStatement($origin, $query, new QualifiedName($parts), $persistence);
    }

    /**
     * Binds MySQL `INTO @var, ...`, `INTO OUTFILE` and `INTO DUMPFILE`; a name without `@` is a stored-program variable.
     * @throws InvalidStructure
     * @throws InvalidSql
     */
    public static function destination(Origin $origin, Node $into, BoundQuery $query, QueryContext $context): BoundStatement
    {
        $destination = Tree::child($into, ['into_destination']) ?? $into;
        $file = Tree::child($destination, ['TEXT_STRING_filesystem']);
        $word = strtoupper($destination->tokens()[0]->text ?? '');
        if ($file !== null && $word === 'DUMPFILE') {
            return new Retrieval\SelectIntoDumpfileStatement($origin, $query, LoadClauses::token($file->tokens()[0] ?? Tree::invalid($file, 'dump file'), $file));
        }
        if ($file !== null) {
            $layout = LoadClauses::layout($destination, $context);
            return new Retrieval\SelectIntoOutfileStatement($origin, $query, LoadClauses::token($file->tokens()[0] ?? Tree::invalid($file, 'export file'), $file), $layout->characterSet, $layout->fields, $layout->lines);
        }
        $variables = [];
        foreach (Tree::outer($destination, ['select_var_ident']) as $target) {
            $tokens = $target->tokens();
            if (($tokens[0]->text ?? '') !== '@' || !isset($tokens[1])) {
                throw new InvalidSql(InputViolation::ProgramReference, $target);
            }
            $variables[] = MySqlNames::read($tokens[1], $context->tables->identifiers);
        }
        return new Retrieval\SelectIntoVariablesStatement($origin, $query, \SqlSemantics\Model\Validation\Collections::nonEmpty($variables));
    }
}
