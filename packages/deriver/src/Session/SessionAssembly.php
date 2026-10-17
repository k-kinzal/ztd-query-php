<?php

declare(strict_types=1);

namespace Deriver\Session;

use Deriver\Candidate\Cache;
use Deriver\Candidate\Index\DefinitionIndex;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Registration\ProviderExpansion;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Lowering\LoweringCache;
use Deriver\Source\Parsing\ParseCache;
use JsonException;

/**
 * Runs the project stages once, in order, and holds their artifacts for the lifetime of a session:
 * provider expansion, model registration, snapshot identity, declaration scanning, and the candidate
 * definition index. No stage is run anywhere else, and no artifact changes after assembly.
 * @visibility namespace
 */
final class SessionAssembly
{
    /**
     * Provider contributions applied to the input and configuration.
     */
    public readonly ProviderExpansion $providers;
    /**
     * Effective captured sources after provider expansion.
     */
    public readonly ProjectInput $input;
    /**
     * Effective configuration after provider expansion.
     */
    public readonly Configuration $configuration;
    /**
     * Registered models, extensions and state slots.
     */
    public readonly Registry $models;
    /**
     * Snapshot identity shared by every reference and result of the session.
     */
    public readonly SnapshotIdentity $identity;
    /**
     * Declarations, with callable bodies lowered on demand.
     */
    public readonly ProjectIndex $program;
    /**
     * Definition, caller and property write indexes for the candidate engine.
     */
    public readonly DefinitionIndex $index;
    /**
     * Bounded cache of candidate expansions shared by the session's queries.
     */
    public readonly Cache $cache;
    /**
     * Published snapshot manifest.
     */
    public readonly ProjectSnapshot $snapshot;

    /**
     * @param ProjectInput $input Captured sources
     * @param Configuration $configuration Explicit assumptions
     * @param ParseCache $parsing Reusable parsed files
     * @param LoweringCache $lowering Reusable lowered bodies
     * @param bool $validateSource Whether malformed captured PHP rejects the session
     * @throws InvalidInputException If the input exceeds its limits or, when validated, contains malformed PHP
     * @throws JsonException If the snapshot identity cannot be encoded
     */
    public function __construct(ProjectInput $input, Configuration $configuration, ParseCache $parsing = new ParseCache(), LoweringCache $lowering = new LoweringCache(), bool $validateSource = true)
    {
        $this->providers = new ProviderExpansion($input, $configuration);
        $this->input = $this->providers->input;
        $this->configuration = $this->providers->configuration;
        $this->configuration->sourceLimits->check($this->input);
        $this->models = new Registry($this->configuration);
        $this->identity = new SnapshotIdentity($this->input, $this->configuration, $this->models, $this->providers);
        $this->program = new ProjectIndex($this->identity->id, $this->input, $this->configuration->target, $parsing, $lowering, $this->configuration->sourceLimits);
        if ($validateSource) {
            $this->validateSource();
        }
        $this->index = new DefinitionIndex($this->program);
        $this->cache = new Cache($this->configuration->candidateCacheEntries);
        $this->snapshot = $this->identity->snapshot($this->program->diagnostics());
    }

    /**
     * Rejects captured PHP the parser could not read.
     * @throws InvalidInputException If any file is malformed
     */
    public function validateSource(): void
    {
        foreach ($this->program->diagnostics() as $diagnostic) {
            if ($diagnostic->code === 'INCOMPLETE_SOURCE') {
                throw new InvalidInputException('Malformed captured PHP in ' . $diagnostic->at->path . ': ' . $diagnostic->operation);
            }
        }
    }
}
