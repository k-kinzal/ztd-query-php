<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Evidence\Node;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Alternative;
use Deriver\Result\CandidateCollection;
use Deriver\Session\Session;
use JsonException;

/**
 * Captures small candidate-only fixtures through the production API.
 */
final class CandidateApi
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function session(string $source = 'function target(){return 42;}', Configuration $configuration = new Configuration(), \Deriver\Model\Registration\Contributions $contributions = new \Deriver\Model\Registration\Contributions()): Session
    {
        return new Session(new ProjectInput([new SourceFile('candidate.php', '<?php ' . $source)]), $configuration, $contributions);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function returns(string $source = 'function target(){return 42;}', Configuration $configuration = new Configuration(), Budget $budget = new Budget(), \Deriver\Model\Registration\Contributions $contributions = new \Deriver\Model\Registration\Contributions()): CandidateCollection
    {
        return self::session($source, $configuration, $contributions)->derive(new ReturnQuery('target', budget: $budget));
    }

    /**
     * Owns a small evidence graph independently from an analysis session.
     */
    public static function proof(Node $root, ?Node $context = null): Alternative
    {
        return new Alternative($root, $context ?? new Node('context-any'), new ProjectSnapshot('s', [], [], new TargetProfile(), false, 'test'));
    }
}
