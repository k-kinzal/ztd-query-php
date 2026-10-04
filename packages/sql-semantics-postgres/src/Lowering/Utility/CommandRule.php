<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Call;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoBlock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\DoLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Listen;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Load;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Notify;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Unlisten;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CALL, DO, NOTIFY, LISTEN, UNLISTEN and LOAD.
 *
 * Rule: PG-COMMAND-LOWER-001. Scope: `CallStmt`, `DoStmt`,
 * `dostmt_opt_list`, `dostmt_opt_item`, `NotifyStmt`, `notify_payload`,
 * `ListenStmt`, `UnlistenStmt`, `LoadStmt`. Constructors: `Call`, `DoBlock`,
 * `DoLanguage`, `Notify`, `Listen`, `Unlisten`, `Load`. The call of CALL is
 * lowered by the invocation family. Termination: the item list is flattened
 * iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-call.html, https://www.postgresql.org/docs/17/sql-do.html,
 * https://www.postgresql.org/docs/17/sql-notify.html, https://www.postgresql.org/docs/17/sql-listen.html,
 * https://www.postgresql.org/docs/17/sql-unlisten.html, https://www.postgresql.org/docs/17/sql-load.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class CommandRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one of the commands.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'CallStmt: CALL func_application' => $this->call($form->node(1)),
            'DoStmt: DO dostmt_opt_list' => new DoBlock($this->items($form->node(1))),
            'NotifyStmt: NOTIFY ColId notify_payload' => new Notify($names->name($form->node(1)), $this->payload($form->node(2))),
            'ListenStmt: LISTEN ColId' => new Listen($names->name($form->node(1))),
            'UnlistenStmt: UNLISTEN ColId' => new Unlisten($names->name($form->node(1))),
            'UnlistenStmt: UNLISTEN *' => new Unlisten(),
            'LoadStmt: LOAD file_name' => new Load($this->lowering->literals->string($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the `func_application` of CALL.
     */
    public function call(Node $application): Call
    {
        $call = $this->lowering->invocations->call($application);
        Check::invariant($call instanceof FunctionCall, 'A function application is a function call.');

        return new Call($call);
    }

    /**
     * Lowers `dostmt_opt_list`: the code strings and language clauses in the order written.
     *
     * @return list<StringConstant|DoLanguage>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function items(Node $list): array
    {
        $items = [];
        foreach ($this->lowering->items($list, 'dostmt_opt_list: dostmt_opt_item', 'dostmt_opt_list: dostmt_opt_list dostmt_opt_item') as $item) {
            $form = $this->lowering->productions->form($item);
            $items[] = match ($form->signature) {
                'dostmt_opt_item: Sconst' => $this->lowering->literals->string($form->node(0)),
                'dostmt_opt_item: LANGUAGE NonReservedWord_or_Sconst' => new DoLanguage($this->lowering->options->wordOrString($form->node(1))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $items;
    }

    /**
     * Lowers `notify_payload`; no payload is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function payload(Node $payload): ?StringConstant
    {
        $form = $this->lowering->productions->form($payload);

        return match ($form->signature) {
            'notify_payload: , Sconst' => $this->lowering->literals->string($form->node(1)),
            'notify_payload:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
