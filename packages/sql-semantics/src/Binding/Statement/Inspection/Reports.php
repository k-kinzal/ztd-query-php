<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Inspection\Engine\EngineReport;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileLimit;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Inspection\Server;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies storage engine reports, session profiles, and parse tree requests.
 * @visibility SqlSemantics
 */
final class Reports
{
    /**
     * Routes by the keyword following SHOW.
     */
    public static function bind(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        return match ($request->word(0)) {
            'ENGINE' => self::engine($origin, $request->form, $context),
            'PROFILES' => new Server\ShowProfilesStatement($origin),
            'PROFILE' => self::profile($origin, $request->form, $context),
            'PARSE_TREE' => self::parseTree($origin, $request->form, $context),
            default => null,
        };
    }

    /**
     * The engine is the token before the report keyword: the ALL keyword, an identifier, or a nonempty string.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function engine(Origin $origin, Node $form, QueryContext $context): Server\ShowEngineReportStatement
    {
        $tokens = $form->tokens();
        $name = $tokens[count($tokens) - 2] ?? Tree::invalid($form, 'engine report');
        $report = $tokens[count($tokens) - 1] ?? Tree::invalid($form, 'engine report');
        $engine = $name->name === 'ALL' ? EngineSelection::All : AccountNames::part($name, $context->tables->identifiers);
        if ($engine === '') {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::EngineName, $name);
        }
        return new Server\ShowEngineReportStatement($origin, $engine, EngineReport::from(strtoupper($report->text)));
    }

    /**
     * Categories keep their request order; the query number stays a literal and the window keeps its operands.
     */
    public static function profile(Origin $origin, Node $form, QueryContext $context): Server\ShowProfileStatement
    {
        $categories = array_map(static fn (Node $category): ProfileCategory => ProfileCategory::from(strtoupper(Tree::text($category))), Tree::outer($form, ['profile_def']));
        $selector = Tree::child($form, ['opt_for_query', 'opt_profile_args']);
        $query = null;
        if ($selector !== null) {
            $tokens = $selector->tokens();
            $query = (new LiteralBinder(Dialect::MySql))->bind($tokens[count($tokens) - 1]);
            if (!$query instanceof Literal) {
                Tree::invalid($selector, 'profiled query');
            }
        }
        return new Server\ShowProfileStatement($origin, $categories, $query, self::limit($form, $context));
    }

    /**
     * A comma window lists the offset first; an OFFSET window lists the count first.
     */
    public static function limit(Node $form, QueryContext $context): ?ProfileLimit
    {
        $clause = Tree::child($form, ['opt_limit_clause', 'opt_limit_clause_init']);
        if ($clause === null) {
            return null;
        }
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $options = array_map(static fn (Node $option): Expression => self::option($option, $scope), Tree::outer($clause, ['limit_option']));
        $first = $options[0] ?? Tree::invalid($clause, 'profile window');
        if (!isset($options[1])) {
            return new ProfileLimit($first);
        }
        return str_contains(Tree::text($clause), ',') ? new ProfileLimit($options[1], $first) : new ProfileLimit($first, $options[1]);
    }

    /**
     * A window operand is a number, a parameter marker, or the name of a stored program variable, which may be spelled with a non-reserved keyword.
     */
    public static function option(Node $option, Scope $scope): Expression
    {
        $name = Tree::child($option, ['ident']);
        if ($name === null) {
            return (new ExpressionBinder())->bind($option, $scope);
        }
        return $scope->column([$scope->identifiers->name($name->tokens()[0])], $name);
    }

    /**
     * Binds the parsed statement with the shared identity allocator, never executing it.
     */
    public static function parseTree(Origin $origin, Node $form, QueryContext $context): Server\ShowParseTreeStatement
    {
        $statement = Tree::child($form, ['simple_statement']);
        if ($statement === null) {
            Tree::invalid($form, 'parsed statement');
        }
        return new Server\ShowParseTreeStatement($origin, (new StatementBinder($context->tables))->node($statement, $statement, $context));
    }
}
