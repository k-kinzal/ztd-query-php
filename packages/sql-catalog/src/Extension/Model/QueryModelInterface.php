<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use PhpParser\Node\Expr;
use SqlCatalog\Evaluation\Domain;

/**
 * Converts a call and its derived inputs into SQL without performing derivation itself.
 *
 * @visibility public
 *
 * @example Compiling a statement from a core-derived input
 *     $model = new class implements \SqlCatalog\Extension\Model\QueryModelInterface {
 *         public function inputs(\PhpParser\Node\Expr\CallLike $call): array { return [$call->getArgs()[0]->value]; }
 *         public function statements(\PhpParser\Node\Expr\CallLike $call, array $values): array {
 *             return [new \SqlCatalog\Extension\Model\QueryOutput(\SqlCatalog\Evaluation\Domain::literal('SELECT * FROM ')->concat($values[0]), \SqlCatalog\Evaluation\Domain::literal(null))];
 *         }
 *     };
 *     $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('read_table'));
 *     $model->statements($call, [\SqlCatalog\Evaluation\Domain::literal('users')])[0]->sql->soleLiteral()->value // => 'SELECT * FROM users'
 */
interface QueryModelInterface
{
    /**
     * @return list<Expr> Expressions to derive together, preserving their correspondence.
     */
    public function inputs(Expr\CallLike $call): array;

    /**
     * @param list<Domain> $values The inputs derived by the core in the requested order.
     * @return list<QueryOutput> Statements or alternatives, including explicit model gaps.
     */
    public function statements(Expr\CallLike $call, array $values): array;
}
