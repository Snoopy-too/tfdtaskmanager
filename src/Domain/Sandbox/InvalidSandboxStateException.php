<?php
declare(strict_types=1);

namespace App\Domain\Sandbox;

/**
 * Raised when a submitted sandbox table state violates the domain schema.
 */
final class InvalidSandboxStateException extends \DomainException
{
}
