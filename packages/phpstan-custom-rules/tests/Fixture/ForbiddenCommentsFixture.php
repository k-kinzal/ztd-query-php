<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class ForbiddenCommentsFixture
{
    public function withForbiddenComments(): void
    {
        // obvious comment
        /** @phpstan-ignore-next-line */
        $this->missingMethodForPhpStan();
        $value = $this->missingMethodForPhpStanLine(); // @phpstan-ignore-line
        /** @infection-ignore-all */
        /* block comment is allowed */
        $value = 1;
    }
}
