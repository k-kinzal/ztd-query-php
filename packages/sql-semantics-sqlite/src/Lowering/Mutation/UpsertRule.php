<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Mutation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;

/**
 * Lowers the upsert tail of an INSERT.
 *
 * Rule: SQLITE-UPSERT-LOWER-001. Scope: upsert. The ON CONFLICT clauses keep
 * their written order; the RETURNING clause ends the tail. Terminates: the
 * chain of clauses is walked in a loop.
 * Source: https://sqlite.org/lang_upsert.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class UpsertRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an `upsert` into its ON CONFLICT clauses and the RETURNING columns.
     *
     * @return array{list<Upsert>, list<ResultColumn|Star|TableStar>}
     * @throws ImplementationGap When a production has no rule
     */
    public function tail(Node $upsert): array
    {
        $lowering = $this->lowering;
        $upserts = [];
        $returning = [];
        for ($node = $upsert; $node !== null;) {
            $form = $lowering->productions->form($node);
            $node = null;
            switch ($form->signature) {
                case 'upsert:':
                    break;
                case 'upsert: RETURNING selcollist':
                    $returning = $lowering->results->columns($form->node(1));
                    break;
                case 'upsert: ON CONFLICT LP sortlist RP where_opt DO UPDATE SET setlist where_opt upsert':
                    $target = new ConflictTarget($lowering->ordering->terms($form->node(3)), $lowering->expressions->where($form->node(5)));
                    $upserts[] = new Upsert($target, $lowering->mutations->assignments($form->node(9)), $lowering->expressions->where($form->node(10)));
                    $node = $form->node(11);
                    break;
                case 'upsert: ON CONFLICT LP sortlist RP where_opt DO NOTHING upsert':
                    $upserts[] = new Upsert(new ConflictTarget($lowering->ordering->terms($form->node(3)), $lowering->expressions->where($form->node(5))));
                    $node = $form->node(8);
                    break;
                case 'upsert: ON CONFLICT DO NOTHING returning':
                    $upserts[] = new Upsert();
                    $returning = $lowering->mutations->returning($form->node(4));
                    break;
                case 'upsert: ON CONFLICT DO UPDATE SET setlist where_opt returning':
                    $upserts[] = new Upsert(null, $lowering->mutations->assignments($form->node(5)), $lowering->expressions->where($form->node(6)));
                    $returning = $lowering->mutations->returning($form->node(7));
                    break;
                default:
                    throw ImplementationGap::production($form);
            }
        }

        return [$upserts, $returning];
    }
}
