<?php

declare(strict_types=1);

require_once './vendor-bin/cs-fixer/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config
	->getFinder()
	->notPath('build')
	->notPath('vendor')
	->notPath('vendor-bin/*/vendor')
	->in(__DIR__);

return $config;
