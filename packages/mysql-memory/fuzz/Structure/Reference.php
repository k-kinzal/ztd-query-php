<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Error\SqlError;
use PDO;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

/**
 * Audits a direct execution's SQL rejection against a fresh native server.
 *
 * A matching error is evidence of native behavior, not a success classification
 * for the construction property. Each observation gets its own disposable server,
 * including inputs that may partly modify state before returning an error.
 */
final class Reference
{
    /**
     * Records whether the native server returns the same error number, SQLSTATE and message.
     *
     * @param string|null $sqlMode The full runtime mode, including flags not retained by the lexical profile
     * @return array<string, mixed> The bound SQL, both outcomes, and exact error equality
     */
    public function rejection(Statement|Scalar $node, LanguageProfile $profile, SqlError $failure, ?int $port = null, ?string $sqlMode = null): array
    {
        $sql = $this->sql($node, $profile);
        $mode = $sqlMode ?? (new Profiles())->mode($profile);
        $definition = new DisposableMySql(substr($profile->grammar->value, 6));
        if ($port !== null) {
            $definition->withPortStrategy(new StaticPortStrategy($port));
        }
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $native->exec('SET SESSION sql_mode = ' . $native->quote($mode));
            $expected = (new Observer())->observe($native, $sql, true);
            $actual = [$failure->getCode(), $failure->sqlState(), $failure->getMessage()];

            return ['sql' => $sql, 'profile' => $profile->grammar->value, 'mode' => $mode, 'native' => $expected, 'memoryError' => $actual, 'matches' => ($expected['error'] ?? null) === $actual];
        } finally {
            $container->stop();
        }
    }

    /**
     * Renders only for the reference, binding each parameter occurrence to the fixture's NULL.
     * Quoted text and identifiers that resemble parameter markers remain unchanged.
     */
    public function sql(Statement|Scalar $node, LanguageProfile $profile, string $binding = 'NULL'): string
    {
        $statement = $node instanceof Statement ? $node : new QueryRoot(new Select([], [new SelectExpression($node)]));
        $out = new Output(Platforms::of('mysql')->codec($profile));
        $statement->render($out);
        $markers = [];
        foreach ($out->parameters() as $parameter) {
            if ($parameter instanceof Parameter) {
                $markers[] = $parameter->marker;
            }
        }
        $canonical = $out->canonical();
        $pieces = [];
        foreach ($out->pieces() as $index => $piece) {
            $original = $canonical[$index];
            $pieces[] = $original->kind === PieceKind::Literal && in_array($original->text, $markers, true)
                ? new Piece($binding === '?' ? PieceKind::Literal : PieceKind::Keyword, $binding, $piece->glued, $piece->gap) : $piece;
        }

        return (new Lexical())->join($pieces) . $out->trailing();
    }
}
