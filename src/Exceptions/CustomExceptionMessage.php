<?php

namespace App\Exceptions;

class CustomExceptionMessage extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
