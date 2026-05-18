<?php

namespace App\Exceptions;

use Exception;

class CapacityExceededException extends Exception
{
    public function __construct(
        public int $current,
        public int $limit,
        string $message = 'Capacity exceeded'
    ) {
        parent::__construct($message);
    }
}
