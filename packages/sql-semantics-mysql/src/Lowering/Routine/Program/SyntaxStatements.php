<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

/**
 * Retains system-variable lookup at completion of a stored-program statement.
 *
 * The existing prefix derivation supplies the lookup. Local-variable and
 * condition references cannot be judged without enclosing scopes and are
 * left to complete-program analysis. No statement is executed here.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxStatements
{
    /**
     * Answers a completed statement's global variable-name refusal.
     *
     * @throws \SqlParser\Lexer\SourceException When a completed instruction reaches its syntax boundary
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a refusal has no token boundary
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['sp_proc_stmt', 'ev_sql_stmt_inner'], true)) {
            return null;
        }
        $prefix = new InputPrefix($lowering);
        $frame = new PrefixFrame();
        try {
            $parameters = $prefix->completed($node, $frame, new ParameterList([]), true);
        } catch (\SqlSemantics\Diagnostic\AnalysisException $failure) {
            throw $failure->getPrevious() instanceof \SqlParser\Lexer\SourceException ? $failure->getPrevious() : $failure;
        }
        if ($parameters instanceof RejectedInput) {
            if ($parameters->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch || $parameters->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems) {
                return null;
            }
            if (($parameters->problem instanceof \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName || $parameters->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns) && !$parameters->aborts) {
                return new ParseFailure($parameters->problem, true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
            }
            return new ParseFailure($parameters->problem, $parameters->aborts);
        }
        $problem = $prefix->diagnostic($frame->body(), $parameters, false);

        return $problem instanceof UnknownSystemVariable ? new ParseFailure($problem, true) : null;
    }
}
