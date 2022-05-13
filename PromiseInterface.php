<?php
namespace Co;

/**
 * A pure promise interface, designed for interoperability. This interface can
 * be implemented by most known promise implementations, including React, Amp
 * and GuzzleHttp..
 */
interface PromiseInterface {

    /**
     * Schedule a callback to run when the promise is fulfilled
     * or rejected.
     *
     * @param callable $onFulfill   Callback which will be invoked if the promise is fulfilled.
     * @param callable $onReject    Callback which will be invoked if the promise is rejected.
     * @param callable $void        Ignored; for compataiblity with other promise implementations.
     * @return PromiseInterface     Returns a new promise which is resolved with the return value of $onFulfill/$onReject
     */
    public function then(callable $onFulfill=null, callable $onReject=null, callable $void=null): PromiseInterface;

    /**
     * Is the promise still pending resolution?
     */
    public function isPending(): bool;

    /**
     * Is the promise fulfilled?
     */
    public function isFulfilled(): bool;

    /**
     * Is the promise rejected?
     */
    public function isRejected(): bool;

}
