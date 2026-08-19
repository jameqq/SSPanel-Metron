<?php

declare(strict_types=1);

use Slim\App;

return static function (App $app) {
    // Slim's native error handler is used. The old slim-whoops middleware pins
    // an unsupported Whoops release with a known XSS vulnerability.
};
