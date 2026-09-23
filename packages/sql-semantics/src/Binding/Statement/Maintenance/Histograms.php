<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Maintenance\Histogram\BucketCount;
use SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Distinguishes histogram sampling, literal-data import and histogram removal.
 * @visibility SqlSemantics
 */
final class Histograms
{
    /**
     * @param non-empty-list<TableReference> $tables Physical table operands from the statement header
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context, array $tables, BinlogPolicy $binlog): Statement\UpdateHistogramStatement|Statement\ImportHistogramStatement|Statement\DropHistogramStatement
    {
        if (count($tables) !== 1) {
            throw new InvalidSql(InputViolation::HistogramTarget, $node);
        }
        $scope = new Scope($context->tables->identifiers, $tables, queries: $context);
        $names = Tree::outer($node, ['ident_string_list'])[0] ?? throw new UnclassifiedSql('Histogram maintenance requires column names.');
        $columns = self::columns($names, $scope);
        if (strtoupper($node->tokens()[0]->text) === 'DROP') {
            return new Statement\DropHistogramStatement($origin, $tables[0], $columns, $binlog);
        }
        $parameters = Tree::outer($node, ['opt_histogram_update_param'])[0] ?? null;
        if ($parameters !== null && strtoupper($parameters->tokens()[0]->text ?? '') === 'USING') {
            if (count($columns) !== 1) {
                throw new InvalidSql(InputViolation::HistogramImport, $node);
            }
            $data = (new LiteralBinder($origin->dialect))->bind($parameters->tokens()[2]);
            if (!$data instanceof Literal) {
                throw new UnclassifiedSql('Histogram data requires a text literal.');
            }
            return new Statement\ImportHistogramStatement($origin, $tables[0], $columns[0], $data, $binlog);
        }
        $buckets = Tree::outer($node, ['opt_histogram_num_buckets'])[0] ?? null;
        if ($buckets === null && $parameters !== null && strtoupper($parameters->tokens()[0]->text ?? '') === 'WITH') {
            $buckets = $parameters;
        }
        $refresh = Tree::outer($node, ['opt_histogram_auto_update'])[0] ?? null;
        return new Statement\UpdateHistogramStatement($origin, $tables[0], $columns, $buckets === null || !Tree::hasTokens($buckets) ? null : self::buckets($buckets), $refresh === null ? RefreshPolicy::Default : RefreshPolicy::from(strtoupper(Tree::text($refresh))), $binlog);
    }

    /**
     * @return non-empty-list<ColumnReference|UnresolvedColumnReference> Bound or diagnosed column identities
     * @throws UnclassifiedSql
     */
    public static function columns(Node $names, Scope $scope): array
    {
        $columns = [];
        foreach (Tree::outer($names, ['ident']) as $name) {
            $column = $scope->column($scope->identifiers->parts($name), $name);
            if (!$column instanceof ColumnReference && !$column instanceof UnresolvedColumnReference) {
                throw new UnclassifiedSql('A histogram column requires a named table column.');
            }
            $columns[] = $column;
        }
        if ($columns === []) {
            throw new UnclassifiedSql('Histogram maintenance requires at least one column.');
        }
        return $columns;
    }

    /**
     * Reads a structural bucket limit, without evaluating SQL expressions or JSON data.
     * @throws InvalidSql
     */
    public static function buckets(Node $node): BucketCount
    {
        $spelling = $node->tokens()[1]->text;
        $digits = ltrim($spelling, '0');
        if (preg_match('/^[0-9]+$/D', $spelling) !== 1 || strlen($digits) > 4 || (int) $digits < 1 || (int) $digits > 1024) {
            throw new InvalidSql(InputViolation::HistogramBuckets, $node);
        }
        return new BucketCount((int) $digits);
    }
}
