<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\ForbiddenCommentRule;

/**
 * @extends RuleTestCase<ForbiddenCommentRule>
 */
#[CoversClass(ForbiddenCommentRule::class)]
#[Medium]
final class ForbiddenCommentRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ForbiddenCommentRule();
    }

    public function testDetectsForbiddenComments(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ForbiddenCommentsFixture.php',
        ], [
            ['Line comments using // are prohibited: "// obvious comment". Refactor to make the comment unnecessary, or delete it. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.', 11],
            ['phpstan-ignore comments are prohibited: "/** @phpstan-ignore-next-line */". Suppressing static analysis hides real problems and weakens CI. Fix the underlying issue instead. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.', 12],
            ['No error to ignore is reported on line 13.', 13],
            ['No error to ignore is reported on line 14.', 14],
            ['Line comments using // are prohibited: "// @phpstan-ignore-line". Refactor to make the comment unnecessary, or delete it. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.', 15],
            ['phpstan-ignore comments are prohibited: "// @phpstan-ignore-line". Suppressing static analysis hides real problems and weakens CI. Fix the underlying issue instead. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.', 15],
            ['infection-ignore-all comments are prohibited: "/** @infection-ignore-all */". Ignoring all mutations hides real quality regressions and weakens CI. Refactor tests and production code so mutation testing can run without this suppression.', 15],
        ]);
    }
}
