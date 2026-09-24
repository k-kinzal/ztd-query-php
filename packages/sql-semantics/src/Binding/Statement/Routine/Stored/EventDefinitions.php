<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Stored;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramBinder;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramFrame;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\EventAlteration;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Program\AlterEventStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE EVENT and ALTER EVENT: schedule, completion policy, status, comment and body.
 * @visibility SqlSemantics
 */
final class EventDefinitions
{
    /**
     * Omitted options are ENABLE and ON COMPLETION NOT PRESERVE.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function create(Origin $origin, Node $tail, QueryContext $context, AccountName|CurrentAccount|null $definer): CreateEventStatement
    {
        $name = StoredPrograms::name(Tree::child($tail, ['sp_name']), $context);
        $schedule = self::schedule(Tree::child($tail, ['ev_schedule_time']) ?? throw new UnclassifiedSql('An event requires its schedule.'), $context);
        $completion = self::completion(Tree::outer($tail, ['ev_on_completion'])[0] ?? null) ?? EventCompletion::Drop;
        $body = self::body(Tree::child($tail, ['ev_sql_stmt']) ?? throw new UnclassifiedSql('An event requires its body.'), $context);
        return new CreateEventStatement($origin, $name, $schedule, $body, $completion, self::status(Tree::child($tail, ['opt_ev_status'])) ?? EventStatus::Enabled, self::comment(Tree::child($tail, ['opt_ev_comment'])), $definer, Tree::child($tail, ['opt_if_not_exists']) !== null);
    }

    /**
     * Requires at least one requested change.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function alter(Origin $origin, Node $statement, QueryContext $context, AccountName|CurrentAccount|null $definer): AlterEventStatement
    {
        $name = StoredPrograms::name(Tree::child($statement, ['sp_name']), $context);
        $timing = Tree::child($statement, ['ev_alter_on_schedule_completion']);
        $schedule = $timing === null ? null : Tree::child($timing, ['ev_schedule_time']);
        $rename = Tree::child($statement, ['opt_ev_rename_to']);
        $action = Tree::child($statement, ['opt_ev_sql_stmt']);
        $bodyNode = $action === null ? null : Tree::child($action, ['ev_sql_stmt']);
        $changes = [
            $schedule === null ? null : self::schedule($schedule, $context),
            $timing === null ? null : self::completion(Tree::child($timing, ['ev_on_completion']) ?? ($timing->name === 'ev_on_completion' ? $timing : null)),
            $rename === null ? null : StoredPrograms::name(Tree::child($rename, ['sp_name']), $context),
            self::status(Tree::child($statement, ['opt_ev_status'])),
            self::comment(Tree::child($statement, ['opt_ev_comment'])),
            $bodyNode === null ? null : self::body($bodyNode, $context),
        ];
        if (array_filter($changes, static fn ($change): bool => $change !== null) === []) {
            throw new InvalidSql(InputViolation::ProgramDefinition, $statement);
        }
        return new AlterEventStatement($origin, $name, new EventAlteration(...$changes), $definer);
    }

    /**
     * Binds AT time or EVERY interval unit with its optional STARTS and ENDS times; microsecond units are diagnosed.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function schedule(Node $node, QueryContext $context): OneTimeSchedule|RecurringSchedule
    {
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $binder = new ExpressionBinder();
        $time = $binder->bind(Tree::child($node, ['expr']) ?? throw new UnclassifiedSql('An event schedule requires its time.'), $scope);
        if (strtoupper($node->tokens()[0]->text ?? '') !== 'EVERY') {
            return new OneTimeSchedule($time);
        }
        $interval = Tree::child($node, ['interval']) ?? throw new UnclassifiedSql('A recurring event requires its interval unit.');
        $unit = MySqlUnit::spelled(implode('', array_map(static fn ($token): string => $token->text, $interval->tokens()))) ?? throw new UnclassifiedSql('Unclassified interval unit.');
        if (str_contains($unit->value, 'MICROSECOND')) {
            throw new InvalidSql(InputViolation::ProgramDefinition, $interval);
        }
        $starts = Tree::child($node, ['ev_starts']);
        $ends = Tree::child($node, ['ev_ends']);
        return new RecurringSchedule($time, $unit, $starts === null ? null : $binder->bind(Tree::child($starts, ['expr']) ?? $starts, $scope), $ends === null ? null : $binder->bind(Tree::child($ends, ['expr']) ?? $ends, $scope));
    }

    /**
     * Reads ON COMPLETION [NOT] PRESERVE.
     */
    public static function completion(?Node $node): ?EventCompletion
    {
        if ($node === null) {
            return null;
        }
        return in_array('NOT', array_map(static fn ($token): string => strtoupper($token->text), $node->tokens()), true) ? EventCompletion::Drop : EventCompletion::Preserve;
    }

    /**
     * Reads ENABLE, DISABLE, or DISABLE ON SLAVE and its synonym DISABLE ON REPLICA.
     */
    public static function status(?Node $node): ?EventStatus
    {
        if ($node === null) {
            return null;
        }
        $tokens = $node->tokens();
        return match (true) {
            strtoupper($tokens[0]->text ?? '') === 'ENABLE' => EventStatus::Enabled,
            count($tokens) > 1 => EventStatus::DisabledOnReplica,
            default => EventStatus::Disabled,
        };
    }

    /**
     * Reads the COMMENT text literal.
     * @throws UnclassifiedSql
     */
    public static function comment(?Node $node): ?Literal
    {
        if ($node === null) {
            return null;
        }
        $comment = (new LiteralBinder(Dialect::MySql))->bind($node->tokens()[1] ?? throw new UnclassifiedSql('COMMENT requires its text.'));
        return $comment instanceof Literal ? $comment : throw new UnclassifiedSql('An event comment requires a text literal.');
    }

    /**
     * Binds the DO body as an event program.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function body(Node $node, QueryContext $context): ProgramStatement
    {
        return ProgramBinder::statement($node, ProgramFrame::start(ProgramKind::Event, $context, new ProgramNamespace()));
    }
}
