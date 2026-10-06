<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Analysis\Session;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Result\Evidence\Alternative;
use Deriver\Result\Evidence\Node;
use JsonException;

/**
 * Captures small candidate-only fixtures through the production API.
 */
final class CandidateApi
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function session(string $source = 'function target(){return 42;}', Configuration $configuration = new Configuration()): Session
    {
        return new Session(new ProjectInput([new SourceFile('candidate.php', '<?php ' . $source)]), $configuration);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function returns(string $source = 'function target(){return 42;}', Configuration $configuration = new Configuration(), Budget $budget = new Budget()): CandidateCollection
    {
        return self::session($source, $configuration)->derive(new ReturnQuery('target', budget: $budget));
    }

    /**
     * Owns a small evidence graph independently from an analysis session.
     */
    public static function proof(Node $root, ?Node $context = null): Alternative
    {
        return new Alternative($root, $context ?? new Node('context-any'), new ProjectSnapshot('s', [], [], new TargetProfile(), false, 'test'));
    }
}
