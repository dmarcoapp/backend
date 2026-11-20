<?php

declare(strict_types=1);

namespace App\Service\Security;

/**
 * A stored secret that cannot be decrypted: it was encrypted with another key,
 * or it is damaged. Whoever set it has to enter it again.
 */
final class UnreadableSecretException extends \RuntimeException {}
