<?php
declare(strict_types=1);

namespace App\Application\Exceptions;

/**
 * Raised when a resource is currently edit-locked by another user (maps to HTTP 423).
 */
final class ResourceLockedException extends \RuntimeException
{
}
