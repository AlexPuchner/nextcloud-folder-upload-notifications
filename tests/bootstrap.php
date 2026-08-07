<?php

declare(strict_types=1);

$nextcloudRoot = getenv('NEXTCLOUD_ROOT');
if (!is_string($nextcloudRoot) || !is_file($nextcloudRoot . '/lib/composer/autoload.php')) {
	throw new RuntimeException('NEXTCLOUD_ROOT must point to an extracted Nextcloud server release');
}

require_once $nextcloudRoot . '/lib/composer/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
