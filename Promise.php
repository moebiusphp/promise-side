<?php
namespace Co;

use Co\Promise\UncaughtPromiseException;

class Promise implements PromiseInterface {

    private int $status = 0;
    private mixed $result = null;
    private array $onFulfilled = [];
    private array $onRejected = [];
    private bool $first = true;
    private static bool $queueRunning = false;
    private static array $queue = [];

    public function __construct(callable $resolveFunction=null) {
        if ($resolveFunction !== null) {
            $resolveFunction($this->fulfill(...), $this->reject(...));
        }
    }

    public function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface {
        $promise = new Promise();
        $promise->first = false;
        if ($this->status !== 2) {
            if ($onFulfill !== null) {
                $this->onFulfilled[] = function($value) use ($promise, $onFulfill) {
                    try {
                        $result = $onFulfill($value);
                        $promise->fulfill($result);
                    } catch (\Throwable $e) {
                        $promise->reject($e);
                    }
                };
            } else {
                $this->onFulfilled[] = $promise->fulfill(...);
            }
        }
        if ($this->status !== 1) {
            if ($onReject !== null) {
                $this->onRejected[] = function($value) use ($promise, $onReject) {
                    try {
                        $result = $onReject($value);
                        $promise->reject($result);
                    } catch (\Throwable $e) {
                        $promise->reject($e);
                    }
                };
            } else {
                $this->onRejected[] = $promise->reject(...);
            }
        }
        if ($this->status !== 0) {
            $this->settle();
        }
        return $promise;
    }

    public function fulfill(mixed $value): void {
        if ($this->status !== 0) {
            return;
        }
        $this->onRejected = [];
        $this->status = 1;
        if ($value instanceof \Throwable) {
            $this->result = $value;
        } else {
            $this->result = new Promise\RejectedException($value);
        }
        $this->settle();
    }

    public function reject(mixed $value): void {
        if ($this->status !== 0) {
            return;
        }
        $this->onFulfilled = [];
        $this->status = 2;
        $this->result = $value;
        $this->settle();
    }

    private function settle(): void {
        if ($this->status === 1) {
            $callbacks = $this->onFulfilled;
            $this->onFulfilled = [];
        } elseif ($this->status === 2) {
            if ($this->first && $this->onRejected === []) {
                // nothing is handling this reject, so we must throw
                $this->first = false;
                throw $this->result;
            }
            $callbacks = $this->onRejected;
            $this->onRejected = [];
        } else {
            throw new \LogicException("Promise is not ready to settle");
        }
        foreach ($callbacks as $callback) {
            self::$queue[] = $callback;
        }
        if (self::$queueRunning === false) {
            self::$queueRunning = true;
            while (self::$queue !== []) {
                $queue = self::$queue;
                self::$queue = [];
                foreach ($queue as $callback) {
                    $callback();
                }
            }
            self::$queueRunning = false;
        }
    }
}
