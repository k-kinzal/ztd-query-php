<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Analysis\Selection\SelectorResolution;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Query\Query as AnalysisQuery;
use JsonException;

/**
 * Revalidates an exported candidate set against captured bytes and versioned rules.
 * This checks this analyzer's origin inventory; it is not a proof of all PHP behavior.
 * @example Revalidating a captured candidate set
 *     $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f(){return 42;}')]);
 *     $config = new \Deriver\Project\Configuration();
 *     $query = new \Deriver\Query\ReturnQuery('f');
 *     $result = (new \Deriver\Session\Session($input, $config))->derive($query);
 *     (new \Deriver\Replay())->verify($result->toJson(), $input, $config, $query);
 *     $result[0]->result // => 42
 * @visibility public
 */
class Replay
{
    /**
     * @param string|AnalysisQuery $query Selector string or a lower-level analysis request
     * @throws InvalidInputException If values, origins, context, or evidence differ
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function verify(string $json, ProjectInput $input, Configuration $configuration, string|AnalysisQuery $query): void
    {
        $actual = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $session = new Session\Session($input, $configuration);
        $request = is_string($query) ? (new SelectorResolution($session->program))->resolve(Query::parse($query)) : $query;
        $fresh = $session->derive($request);
        $expected = json_decode($fresh->toJson(), true, 512, JSON_THROW_ON_ERROR);
        if ($actual !== $expected) {
            throw new InvalidInputException('EVIDENCE_MISMATCH: the exported values or derivations do not match this source snapshot, query and rule manifest.');
        }
    }
}
