<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server\Change;

use Closure;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Server\Channels;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\Source\IgnoredServers;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSetting;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSettings;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CHANGE MASTER TO, CHANGE REPLICATION SOURCE TO and CHANGE REPLICATION FILTER.
 * @visibility SqlSemantics
 */
final class Changes
{
    /**
     * Distinguishes the filter form by its FILTER keyword.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $channel = Channels::read($node, $context->tables->identifiers);
        if (strtoupper($node->tokens()[2]->text ?? '') === 'FILTER') {
            $filters = [];
            foreach (Tree::outer($node, ['filter_def']) as $definition) {
                $filter = FilterDefinitions::read($definition, $context->tables->identifiers);
                $filters[$filter->rule()->value] = $filter;
            }
            return self::diagnose(static fn (): BoundStatement => new Statement\ChangeReplicationFilterStatement($origin, array_values($filters), $channel), $node);
        }
        $settings = self::effective(array_map(static fn (Node $definition): SourceSetting => SourceDefinitions::read($definition, $context->tables->identifiers), Tree::outer($node, ['source_def', 'master_def'])));
        try {
            SourceSettings::coordinates($settings);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SourceCoordinates, $node, $error);
        }
        $release = ReplicationRelease::number($context->tables->schema->grammarVersion);
        return self::diagnose(static function () use ($origin, $settings, $channel, $release): BoundStatement {
            SourceSettings::password($release, array_values($settings));
            return new Statement\ChangeReplicationSourceStatement($origin, array_values($settings), $channel);
        }, $node);
    }

    /**
     * Keeps the last assignment of each option, as MySQL does, except that IGNORE_SERVER_IDS lists accumulate.
     * @param list<SourceSetting> $settings
     * @return array<string, SourceSetting>
     * @throws InvalidStructure
     */
    public static function effective(array $settings): array
    {
        $options = [];
        foreach ($settings as $setting) {
            $name = $setting->option()->value;
            $previous = $options[$name] ?? null;
            $options[$name] = $setting instanceof IgnoredServers && $previous instanceof IgnoredServers ? new IgnoredServers([...$previous->servers, ...$setting->servers]) : $setting;
        }
        return $options;
    }

    /**
     * Diagnoses an option value the server rejects.
     * @param Closure(): BoundStatement $build
     * @throws InvalidSql
     */
    public static function diagnose(Closure $build, Node $node): BoundStatement
    {
        try {
            return $build();
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ReplicationOption, $node, $error);
        }
    }
}
