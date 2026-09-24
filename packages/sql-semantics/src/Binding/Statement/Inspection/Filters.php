<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use Closure;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Editing\StatementContext;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Reads the restriction, database, and table operands shared by SHOW listings.
 * @visibility SqlSemantics
 */
final class Filters
{
    /**
     * Builds the listing twice: first unrestricted and placed in its schema, to learn the result fields its WHERE condition may refer to, then with the restriction.
     * @param Closure(PatternFilter|ConditionFilter|null): InspectionStatement $build Builds the listing from its restriction
     */
    public static function restrict(Closure $build, Node $form, QueryContext $context): InspectionStatement
    {
        $preview = $build(null)->withContext(new StatementContext($context->tables->schema));
        return $build(self::read($form, $context, $preview->resultColumns()));
    }

    /**
     * Reads a LIKE pattern or WHERE condition; null when the listing is unrestricted.
     * @param list<OutputColumn> $outputs Result fields a condition refers to by label
     */
    public static function read(Node $form, QueryContext $context, array $outputs): PatternFilter|ConditionFilter|null
    {
        $clause = Tree::child($form, ['opt_wild_or_where', 'wild_and_where', 'opt_where_clause', 'where_clause', 'opt_wild_or_where_for_show']);
        if ($clause === null) {
            return null;
        }
        $tokens = $clause->tokens();
        if (strtoupper($tokens[0]->text ?? '') === 'LIKE') {
            $literal = (new LiteralBinder(Dialect::MySql))->bind($tokens[count($tokens) - 1]);
            if (!$literal instanceof Literal) {
                Tree::invalid($clause, 'metadata pattern');
            }
            return new PatternFilter($literal);
        }
        return new ConditionFilter(self::condition($clause, $context, $outputs));
    }

    /**
     * Binds the WHERE predicate over the listing's result fields.
     * @param list<OutputColumn> $outputs Result fields the predicate refers to by label
     */
    public static function condition(Node $clause, QueryContext $context, array $outputs): Expression
    {
        $expression = Tree::outer($clause, ['expr'])[0] ?? null;
        if ($expression === null) {
            Tree::invalid($clause, 'metadata condition');
        }
        return (new ExpressionBinder())->bind($expression, new Scope($context->tables->identifiers, queries: $context, outputs: $outputs));
    }

    /**
     * Reads the FROM or IN database selector; null selects the current database.
     */
    public static function database(Node $form, QueryContext $context): ?string
    {
        $database = Tree::child($form, ['opt_db']);
        if ($database === null) {
            return null;
        }
        $tokens = $database->tokens();
        return $context->tables->identifiers->name($tokens[count($tokens) - 1]);
    }

    /**
     * Resolves the inspected table, letting a trailing FROM or IN database override the table's own qualifier as the server does.
     */
    public static function table(Origin $origin, Node $form, QueryContext $context): TableReference
    {
        $name = Tree::child($form, ['table_ident']);
        if ($name === null) {
            Tree::invalid($form, 'inspected table');
        }
        $parts = $context->tables->identifiers->parts($name);
        $database = self::database($form, $context);
        $last = $parts[count($parts) - 1] ?? Tree::invalid($name, 'inspected table');
        if ($database !== null) {
            $parts = [$database, $last];
        }
        $declaration = $context->tables->resolve($parts, $name);
        return new TableReference($context->ids->relation(), $origin->scopeId, $declaration, $context->tables->name($parts, $declaration), null, $name);
    }
}
