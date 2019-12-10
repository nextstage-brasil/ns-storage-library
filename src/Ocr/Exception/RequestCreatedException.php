<?php

namespace NsStorageLibrary\Ocr\Exception;

use Exception;
use Throwable;

class RequestCreatedException extends Exception {

    public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

}
