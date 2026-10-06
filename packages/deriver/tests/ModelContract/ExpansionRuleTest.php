<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Analyzer;
use Deriver\Model\Expansion\Rule;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that replacements select their inputs before source expansion.
 */
#[CoversNothing]
#[Large]
final class ExpansionRuleTest extends TestCase
{
    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testO01ThroughO03ReplacementDoesNotExpandChildren(): void
    {
        foreach ([['count(expensive())', Rule::constantFunction('count-is-one', '1', 'count', Term::constant(1)), 1], ['1+expensive()', Rule::constantExpression('seven', '1', 'binary', '+', Term::constant(7)), 7], ['42', Rule::constantExpression('literal', '1', 'constant', '', Term::constant(7)), 7]] as [$expression, $rule, $expected]) {
            $source = '<?php function expensive(){return missing();} function f(){return ' . $expression . ';}';
            $session = (new Analyzer())->open(new ProjectInput([new SourceFile('rule.php', $source)]), new Configuration(expansionRules: [$rule]));
            $result = $session->derive(new ReturnQuery('f'));
            self::assertSame($expected, $result->candidates[0]->result);
            self::assertSame([], $result->statistics->expandedBodies);
            $nodes = $result->candidates[0]->evidence[0]->nodes();
            $applications = array_values(array_filter($nodes, static fn ($node): bool => $node->kind === 'model-application'));
            self::assertCount(1, $applications);
            self::assertSame($rule->id, $applications[0]->attributes['id']);
            self::assertSame([], $applications[0]->inputs);
        }
    }
}
