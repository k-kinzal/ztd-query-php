<?php

declare(strict_types=1);

namespace Spec\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Deriver\Deriver;
use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use JsonException;
use PHPUnit\Framework\Assert;
use UnexpectedValueException;

/**
 * Exercises the public derivation API against captured PHP source, without executing fixtures.
 */
final class PhpContext implements Context
{
    /** @var list<string> */
    private array $files = [];

    private string $version = '8.1';

    private bool $branchPruning = false;

    /** @var array<string, string> */
    private array $environment = [];

    private ?CandidateCollection $result = null;

    /**
     * Selects a checked-in source fixture and its PHP language version.
     */
    #[Given('PHP :version source :fixture')]
    public function source(string $version, string $fixture): void
    {
        $this->version = $version;
        $this->files[] = dirname(__DIR__) . '/Fixture/' . $fixture;
        Assert::assertFileExists($this->files[array_key_last($this->files)]);
    }

    /**
     * Supplies external input explicitly instead of reading the host environment.
     */
    #[Given('environment :name equals :value')]
    public function environment(string $name, string $value): void
    {
        $this->environment[$name] = $value;
    }

    /**
     * Installs the optional truth-candidate evaluator through public configuration.
     */
    #[Given('branch pruning is enabled')]
    public function branchPruning(): void
    {
        $this->branchPruning = true;
    }

    /**
     * Verifies that closed coverage leaves unnecessary dependencies unopened.
     */
    #[Then('the body :symbol is not expanded')]
    public function unopened(string $symbol): void
    {
        Assert::assertNotNull($this->result);
        Assert::assertArrayNotHasKey($symbol, $this->result->statistics->expandedBodies);
    }

    /**
     * Uses exactly the preparation and query API available to a library consumer.
     * @throws JsonException If an environment expectation is malformed
     * @throws UnexpectedValueException If an environment value is not a native input
     */
    #[When('I derive :query')]
    public function derive(string $query): void
    {
        $environment = [];
        foreach ($this->environment as $name => $value) {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded) && !is_scalar($decoded) && $decoded !== null) {
                throw new UnexpectedValueException('An environment fixture must contain a native scalar, array or null.');
            }
            $environment[$name] = $decoded;
        }
        $this->result = Deriver::prepare($this->files, new DeriverConfig(phpVersion: $this->version, environment: $environment, evaluators: $this->branchPruning ? BranchEvaluators::truth() : []))->derive($query);
        Assert::assertFalse($this->result->interrupted, 'The isolated feature must complete within the default exploration budget.');
    }

    /**
     * Compares the entire concrete candidate set, including native scalar types and array keys.
     * @throws JsonException If an expected value is malformed
     */
    #[Then('the candidates are:')]
    public function candidates(TableNode $expected): void
    {
        Assert::assertNotNull($this->result);
        $actual = [];
        foreach ($this->result as $candidate) {
            Assert::assertSame(CandidateStatus::Analyzed, $candidate->type);
            $actual[] = serialize($candidate->result);
        }
        $values = [];
        foreach ($expected->getHash() as $row) {
            $values[] = serialize(json_decode($row['value'], true, flags: JSON_THROW_ON_ERROR));
        }
        sort($actual);
        sort($values);
        Assert::assertSame($values, $actual);
    }

    /**
     * Compares complete values and retained expression boundaries in one candidate set.
     * @throws JsonException If an expected value is malformed
     */
    #[Then('the candidates retain:')]
    public function retained(TableNode $expected): void
    {
        Assert::assertNotNull($this->result);
        $actual = [];
        foreach ($this->result as $candidate) {
            $value = $candidate->result;
            $actual[] = serialize($value instanceof Term
                ? ['kind' => $value->kind->value, 'reason' => $value->attributes['reason'] ?? null]
                : ['value' => $value]);
        }
        $values = [];
        foreach ($expected->getHash() as $row) {
            $values[] = serialize($row['kind'] === ''
                ? ['value' => json_decode($row['value'], true, flags: JSON_THROW_ON_ERROR)]
                : ['kind' => $row['kind'], 'reason' => $row['reason']]);
        }
        sort($actual);
        sort($values);
        Assert::assertSame($values, $actual);
    }

    /**
     * Checks that a terminating throw produces no ordinary return value.
     */
    #[Then('the candidate has no return value')]
    public function noReturnValue(): void
    {
        Assert::assertNotNull($this->result);
        Assert::assertCount(1, $this->result);
        Assert::assertInstanceOf(Term::class, $this->result[0]->result);
        Assert::assertSame(TermKind::NoValue, $this->result[0]->result->kind);
    }

    /**
     * Checks termination that cannot reach an ordinary return.
     */
    #[Then('there are no candidates')]
    public function noCandidates(): void
    {
        Assert::assertNotNull($this->result);
        Assert::assertCount(0, $this->result);
    }
}
