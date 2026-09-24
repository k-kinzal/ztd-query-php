<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;

/**
 * Writes subscription commands with options in declaration order and canonical values.
 * @visibility SqlSemantics
 */
final class Subscriptions
{
    /**
     * Returns null for other statements.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\CreateSubscriptionStatement) {
            return new Tree('create-subscription', [Build::keyword('CREATE SUBSCRIPTION'), Build::identifier([$statement->name], Dialect::PostgreSql), Build::keyword('CONNECTION'), self::text($statement->connection), Build::keyword('PUBLICATION'), self::names($statement->publications), ...self::options($statement->options, true)]);
        }
        if ($statement instanceof Statement\DropSubscriptionStatement) {
            return new Tree('drop-subscription', [Build::keyword($statement->ifExists ? 'DROP SUBSCRIPTION IF EXISTS' : 'DROP SUBSCRIPTION'), Build::identifier([$statement->name], Dialect::PostgreSql), ...($statement->behavior->value === '' ? [] : [Build::keyword($statement->behavior->value)])]);
        }
        return match (true) {
            $statement instanceof Statement\AlterSubscriptionConnectionStatement => self::alter($statement->name, [Build::keyword('CONNECTION'), self::text($statement->connection)]),
            $statement instanceof Statement\AlterSubscriptionOptionsStatement => self::alter($statement->name, [Build::keyword('SET'), ...self::options($statement->options, false)]),
            $statement instanceof Statement\AlterSubscriptionPublicationsStatement => self::alter($statement->name, [Build::keyword($statement->change->value . ' PUBLICATION'), self::names($statement->publications), ...self::options($statement->options, true)]),
            $statement instanceof Statement\RefreshSubscriptionStatement => self::alter($statement->name, [Build::keyword('REFRESH PUBLICATION'), ...self::options($statement->options, true)]),
            $statement instanceof Statement\AlterSubscriptionEnabledStatement => self::alter($statement->name, [Build::keyword($statement->enabled ? 'ENABLE' : 'DISABLE')]),
            $statement instanceof Statement\SkipSubscriptionTransactionStatement => self::alter($statement->name, [Build::keyword('SKIP'), Build::parentheses(new Tree('option', [Build::keyword('lsn ='), $statement->lsn === null ? Build::keyword('NONE') : self::text($statement->lsn)]))]),
            default => null,
        };
    }

    /**
     * ALTER SUBSCRIPTION with the named subscription and its change.
     * @param list<Tree> $change
     */
    public static function alter(string $name, array $change): Tree
    {
        return new Tree('alter-subscription', [Build::keyword('ALTER SUBSCRIPTION'), Build::identifier([$name], Dialect::PostgreSql), ...$change]);
    }

    /**
     * Publication names as identifiers.
     * @param non-empty-list<string> $names
     */
    public static function names(array $names): Tree
    {
        return Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::PostgreSql), $names));
    }

    /**
     * A standard string constant.
     */
    public static function text(string $value): Tree
    {
        return new Tree('literal', [new Atom('literal', Literal::encode($value, Dialect::PostgreSql)[0])]);
    }

    /**
     * The specified options in a parenthesized list, with WITH for a definition; nothing when none is specified.
     * @return list<Tree>
     */
    public static function options(Operand\SubscriptionOptions $options, bool $with): array
    {
        $items = [];
        foreach ($options->parameters() as $parameter) {
            $value = $options->value($parameter);
            $items[] = new Tree('option', [Build::keyword($parameter->value . ' ='), match (true) {
                is_bool($value) => Build::keyword($value ? 'true' : 'false'),
                $value instanceof Operand\NoSlot => Build::keyword($value->value),
                default => self::text((string) $value),
            }]);
        }
        return $items === [] ? [] : [...($with ? [Build::keyword('WITH')] : []), Build::parentheses(Build::separated($items))];
    }
}
