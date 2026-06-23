<?php

namespace App\Exceptions;

use RuntimeException;

class CoachNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ollama is not configured. Set it up at /settings/coach.');
    }
}
