<?php
namespace Co\Promise;

use Co\Promise;

class RejectedPromise extends Promise {

    public function __construct(mixed $reason=null) {
        parent::__construct();
        $this->reject($value);
    }

}
