<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CHANGE REPLICATION SOURCE TO option = value, … [FOR CHANNEL 'name']`, written `CHANGE MASTER TO` in the legacy vocabulary.
 *
 * Mirrors SQLCOM_CHANGE_REPLICATION_SOURCE with its LEX_SOURCE_INFO: the
 * options in written order, the same option possibly written more than once
 * (the server keeps the last value). The vocabulary is the one of the
 * release (MYSQL-REPLICATION-RELEASE-001); in 8.0 to 8.3 CHANGE MASTER and
 * the MASTER_ options are read as their current synonyms. A channel needs
 * MySQL 5.7 or later. Rule: MYSQL-CHANGE-SOURCE-001. Facts: the option checks
 * of MYSQL-REPLICATION-SOURCE-001 and a line feed in the channel name. The
 * statement uses no table and provides no declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html,
 * https://dev.mysql.com/doc/refman/5.7/en/change-master-to.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the options of the legacy spelling as their current synonyms
 *     $change = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.0.44'))->analyze("CHANGE MASTER TO MASTER_HOST = 'h', MASTER_PORT = 3307 FOR CHANNEL 'c'");
 *     [$change->toString(), $change->statement->options[1]->kind] // => ["CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 3307 FOR CHANNEL 'c'", \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Port]
 */
final class ChangeReplicationSource implements Statement
{
    use Snapshot;

    /**
     * @var list<SourceOption> The options in written order; at least one
     */
    public readonly array $options;

    /**
     * @param Terminology $terminology The vocabulary the statement is written in
     * @param list<SourceOption> $options The options in written order; at least one, each in the vocabulary of the statement
     * @param Text|null $channel The replication channel, when FOR CHANNEL is written
     */
    public function __construct(public readonly Terminology $terminology, array $options, public readonly ?Text $channel = null)
    {
        $this->options = Check::listOf($options, SourceOption::class, 'CHANGE REPLICATION SOURCE sets a list of options.', 1);
        foreach ($this->options as $option) {
            Check::input($option->terminology === $terminology, 'An option is written in the vocabulary of its statement.');
        }
    }

    /**
     * Checks the vocabulary of the release and derives the options and the channel.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $releases = new Releases();
        $release = $derivation->context->profile->grammar;
        Check::input($releases->source($release) === $this->terminology, 'CHANGE REPLICATION SOURCE is written in the vocabulary of the release.');
        Check::input($this->channel === null || $release !== GrammarRelease::MySql5651, 'A replication channel needs MySQL 5.7 or later.');
        $settings = new SourceSettings();
        $settings->options($derivation, $this->options);
        $settings->lineFeed($derivation, $this->channel);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHANGE');
        if ($this->terminology === Terminology::Legacy) {
            $out->keyword('MASTER');
        } else {
            $out->keyword('REPLICATION', 'SOURCE');
        }
        $out->keyword('TO')->list($this->options);
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
