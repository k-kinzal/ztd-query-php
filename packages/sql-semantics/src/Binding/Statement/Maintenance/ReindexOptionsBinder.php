<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Maintenance\ReindexOptions;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the finite rebuild options and their declared boolean or name domains.
 * @visibility SqlSemantics
 */
final class ReindexOptionsBinder
{
    /**
     * @throws InvalidSql
     */
    public static function bind(Node $node, Scope $scope): ReindexOptions
    {
        $concurrently = false;
        $verbose = false;
        $tablespace = null;
        foreach (Tree::outer($node, ['utility_option_elem']) as $option) {
            $tokens = $option->tokens();
            $name = strtoupper($tokens[0]->text);
            if ($name === 'TABLESPACE' && count($tokens) === 2) {
                $tablespace = $scope->identifiers->name($tokens[1]);
                continue;
            }
            $value = count($tokens) === 1 ? true : match (strtolower(trim($tokens[1]->text, "'"))) {
                'true', 'on', '1' => true,
                'false', 'off', '0' => false,
                default => throw new InvalidSql(InputViolation::ReindexOption, $option),
            };
            if ($name === 'CONCURRENTLY') {
                $concurrently = $value;
            } elseif ($name === 'VERBOSE') {
                $verbose = $value;
            } else {
                throw new InvalidSql(InputViolation::ReindexOption, $option);
            }
        }
        return new ReindexOptions($concurrently || Tree::child($node, ['opt_concurrently']) !== null, $verbose, $tablespace);
    }
}
