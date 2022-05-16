<?php
namespace Co;

use Co\Promise\UncaughtPromiseException;

class Promise implements PromiseInterface {

    public static function isThenable(object $promise): bool {
        return self::isPromise($promise);
    }

    public static function isPromise(object $promise): bool {
        if (!\method_exists($promise, 'then')) {
            return false;
        }

        if (
            $promise instanceof PromiseInterface ||
            (class_exists(\GuzzleHttp\Promise\PromiseInterface::class, false) && $promise instanceof \GuzzleHttp\Promise\PromiseInterface) ||
            (class_exists(\React\Promise\PromiseInterface::class, false) && $promise instanceof \React\Promise\PromiseInterface) ||
            (class_exists(\Http\Promise\Promise::class, false) && $promise instanceof \Http\Promise\Promise)
        ) {
            return true;
        }

        $rm = new \ReflectionMethod($promise, 'then');
        foreach ($rm->getParameters() as $index => $rp) {
            if ($rp->hasType()) {
                $rt = $rp->getType();
                if ($rt instanceof \ReflectionNamedType) {
                    if (
                        $rt->getName() !== 'mixed' &&
                        $rt->getName() !== 'callable' &&
                        $rt->getName() !== \Closure::class
                    ) {
                        return false;
                    }
                }
            }
            if ($rp->isVariadic()) {
                return true;
            }
            if ($index === 1) {
                return true;
            }
        }

        return false;
    }

    public static function cast(object $promise): PromiseInterface {
        if ($promise instanceof PromiseInterface) {
            return $promise;
        }
        if (!self::isPromise($promise)) {
            throw new \TypeError("Expected a promise-like object");
        }
        $result = new Promise();
        return $promise->then($result->fulfill(...), $result->reject(...));
    }

    private int $status = 0;
    private mixed $result = null;
    private array $onFulfilled = [];
    private array $onRejected = [];
    private bool $errorDelivered = false;
    private static bool $queueRunning = false;
    private static array $queue = [];

    public function __construct(callable $resolveFunction=null) {
        if ($resolveFunction !== null) {
            // Exceptions thrown in the resolve-function are not caught
            // because the error belongs to the place where the promise
            // is created. If the exception needs to go to the receiver
            // of the promise - call $reject(new \Exception()) for example.
            $resolveFunction($this->fulfill(...), $this->reject(...));
        }
    }

    public function __destruct() {
        if ($this->status === 2 && !$this->errorDelivered) {
            if ($this->result instanceof \Throwable) {
                throw new Promise\UnhandledException("A promise was rejected without an error handler", 0, $this->result);
            } else {
                throw new Promise\UnhandledException("A promise was rejected with the value ".json_encode($this->result)." without an error handler", 1);
            }
            throw $this->result;
        }
    }

    public function isPending(): bool {
        return $this->status === 0;
    }

    public function isFulfilled(): bool {
        return $this->status === 1;
    }

    public function isRejected(): bool {
        return $this->status === 2;
    }

    public function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface {
        $promise = new Promise();
        $promise->errorDelivered = &$this->errorDelivered;
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
                    $this->errorDelivered = true;
                    try {
                        $result = $onReject($value);
                        $promise->fulfill($result);
                    } catch (\Throwable $e) {
                        $promise->reject($e);
                    }
                };
            } else {
                // that promise shares error delivery with this promise
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
        $this->result = $value;
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
            $callbacks = $this->onRejected;
            $this->onRejected = [];
        } else {
            throw new \LogicException("Promise is not ready to settle");
        }
        foreach ($callbacks as $callback) {
            $callback($this->result);
        }
    }
}
