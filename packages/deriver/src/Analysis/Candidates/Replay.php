<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Analyzer;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Query\Query;
use JsonException;

/**
 * Revalidates an exported candidate set against captured bytes and versioned rules.
 * This checks this analyzer's origin inventory; it is not a proof of all PHP behavior.
 * @example Revalidating a captured candidate set
 *     $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f(){return 42;}')]);
 *     $config = new \Deriver\Project\Configuration();
 *     $query = new \Deriver\Query\ReturnQuery('f');
 *     $result = (new \Deriver\Analyzer())->open($input, $config)->derive($query);
 *     (new \Deriver\Analysis\Candidates\Replay())->verify($result->toJson(), $input, $config, $query);
 *     $result->candidates[0]->result // => 42
 * @visibility public
 */
final class Replay
{
    /**
     * @throws InvalidInputException If values, origins, context, or evidence differ
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function verify(string $json, ProjectInput $input, Configuration $configuration, Query $query): void
    {
        $actual = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $fresh = (new Analyzer())->open($input, $configuration)->derive($query);
        $expected = json_decode($fresh->toJson(), true, 512, JSON_THROW_ON_ERROR);
        if ($actual !== $expected) {
            throw new InvalidInputException('EVIDENCE_MISMATCH: the exported values or derivations do not match this source snapshot, query and rule manifest.');
        }
    }
}
