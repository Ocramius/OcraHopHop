# OcraHopHop - Worker thread HTTP response dispatching for ZF2

OcraHopHop is a project aimed at reducing latency and resources
used by a ZF2 Application while dispatching an HTTP Request.

## Installation

The recommended way to install `ocramius/ocra-hop-hop` is through
[composer](http://getcomposer.org/):

```
{
    "require": {
        "ocramius/ocra-hop-hop": "*"
    }
}
```

## Working concept

The concept behind OCraHopHop is simple, and can be summarized in
following pseudo code:

```php
init_autoload();
init_config();
// ...
init_application();

while ($request = get_http_request()) {
    $application->serve($request);
}
```

The idea is to avoid initialization logic by re-using
resources across multiple requests.

PHP was thought for share-nothing architectures, but for greater
and more complex applications, such an approach is necessary.

If you already know FastCGI, this is what it is all about.

## Usage

 1. Replace your `public/index.php` with the `examples/zf2-server.php`
    that you can find in OcraHopHop.
 2. Copy `examples/zf2-worker.php` to your `public/` dir
 3. Open a terminal, `cd` to your `public/` dir and run
    `php zf2-worker.php`
 4. Run `siege` or `ab -k` against your ZF application
 5. ...?
 6. Profit!