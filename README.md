Co\Promise
==========

A pure and well tested promise implementation designed for interoperability.

Casting a promise
-----------------

Promises from other implementations can be cast to Co\Promise.

```
<?php
$promise = Co\Promise::cast($otherPromise);
```

Identifying a promise-like object
---------------------------------

Since there is no canonical PHP promise interface, it is useful to have a
simple way to check if an object appears to be a valid promise.

```
<?php
if (Co\Promise::isPromise($otherPromise)) {
    // promise has a valid `then()` method
}
```

Creating a promise
------------------

Promises can generally be created in two ways; with a resolver function
or without a resolver function - in which case the promise must be resolved
by calling the `fulfill()` or the `reject()` method.

*With a resolver function**

```
<?php
$promise = new Promise(function(callable $fulfill, callable $reject) {
    $fulfill("Hello World");
});
$promise->then(function($value) {
    echo $value."\n";
});
```

*Without a resolver function*

```
<?php
$promise = new Promise();
$promise->then(function($value) {
    echo $value."\n";
});
$promise->resolve("Hello World");
```


Interoperability Notes
----------------------

The interface is focused on the resolution of promises; it does not care about
how a promise is created - the interface is for functions that accept a promise.

Most promise implementations have an extensive API, but a promise only needs
to expose a `then()` function to be usable by 90% of libraries.

It is also very helpful to have a way to determine if a promise is already
resolved or rejected - which generally is implemented using a method which
returns a string "pending", "resolved", "rejected" or "fulfilled".

This library implements methods `isPending()`, `isResolved()` and `isFulfilled()`
because these methods can be implemented regardless of how the underlying
promise implementation records that state..

Many promise implementations have methods like `otherwise()`, `finally()` and
similar - which may be convenient, but it reduces interoperability because
the same features can be implemented in several ways and with different names.
