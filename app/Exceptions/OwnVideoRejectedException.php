<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An own video outside the recommended format, refused with a reason the dono can act on (US-4.2).
 */
class OwnVideoRejectedException extends RuntimeException {}
