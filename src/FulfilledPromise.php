<?php
namespace Co\Promise;

use Co\Promise;

class FulfilledPromise extends Promise {

    public function __construct(mixed $value=null) {
        parent::__construct();
        $this->fulfill($value);
    }

}
