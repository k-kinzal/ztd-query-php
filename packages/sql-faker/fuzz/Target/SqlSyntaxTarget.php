<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use Closure;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Fuzz\Input\FuzzPlanDecoder;
use SqlFaker\Fuzz\Run\FuzzCheckpoint;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\LexicalException;

/**
 * Applies decode -> ordinary provider generation -> fixed database verification.
 */
final class SqlSyntaxTarget
{
    /**
     * @param Closure(GenerationPlan<true>): non-empty-string $generate
     * @param Closure(string, string): VerificationResult $verify
     */
    public function __construct(
        private readonly Closure $generate,
        private readonly FuzzPlanDecoder $decoder,
        private readonly Closure $verify,
        private readonly FuzzCheckpoint $checkpoint
    ) {
    }

    /**
     * Verifies exactly one generated SQL statement without filtering or re-generation.
     *
     * @throws GenerationException When derivation fails
     * @throws LexicalException When lexical realization fails
     * @throws SyntaxFailure When the DB finds invalid SQL
     */
    public function __invoke(string $input): void
    {
        $sql = null;
        $completed = false;
        $reported = false;
        try {
            $plan = $this->decoder->decode($input);
            $sql = ($this->generate)($plan);
            $result = ($this->verify)($sql, bin2hex($input));
            $this->checkpoint->observe($result, $input, $sql);
            $completed = true;
        } catch (CoverageException $failure) {
            $reported = true;
            fwrite(STDERR, 'Coverage infrastructure failure: ' . $failure->getMessage() . "\n");
            exit(2);
        } catch (InfrastructureFailure $failure) {
            $reported = true;
            $this->checkpoint->failure($input, $sql, $failure);
            fwrite(STDERR, 'Database infrastructure failure: ' . $failure->getMessage() . "\n");
            exit(2);
        } catch (GenerationException|LexicalException|SyntaxFailure $failure) {
            $reported = true;
            $this->checkpoint->failure($input, $sql, $failure);
            throw $failure;
        } finally {
            if (!$completed && !$reported) {
                $this->checkpoint->interrupted($input, $sql);
            }
        }
    }
}
