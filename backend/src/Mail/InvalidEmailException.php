<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** A rendered email failed a safety check (e.g. a subject containing a line break). */
final class InvalidEmailException extends RuntimeException
{
}
