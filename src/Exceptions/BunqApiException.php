<?php

namespace Siberfx\LaravelBunq\Exceptions;

use RuntimeException;

/**
 * Anything that goes wrong talking to bunq, wrapped so callers only need to
 * catch one exception type.
 */
class BunqApiException extends RuntimeException {}
