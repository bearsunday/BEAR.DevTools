<?php

declare(strict_types=1);

use MyVendor\MyProject\Bootstrap;


require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__, 4) . '/vendor/autoload.php';
exit((new Bootstrap())(PHP_SAPI === 'cli-server' ? 'dev-app' : 'dev-prod-app', $GLOBALS, $_SERVER));
