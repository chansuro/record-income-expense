<?php

namespace App\Exceptions;

use RuntimeException;

class HmrcSubmissionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $hmrcCode = null,
        public readonly ?string $correlationId = null,
        public readonly string $outcome = 'unknown',
    ) {
        parent::__construct($message);
    }
}
