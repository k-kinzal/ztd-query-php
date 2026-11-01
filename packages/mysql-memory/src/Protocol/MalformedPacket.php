<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

use RuntimeException;

/**
 * A packet from the client that does not follow the protocol.
 *
 * @visibility MySqlMemory
 */
final class MalformedPacket extends RuntimeException
{
}
