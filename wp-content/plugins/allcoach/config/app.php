<?php

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;

return [
	'name'        => 'All Coach',
	'version'     => Constants::VERSION,
	'slug'        => 'allcoach',
	'text_domain' => Constants::TEXT_DOMAIN,
	'namespace'   => 'allcoach/v1',

	'paths'       => [
		'base'      => Constants::pluginPath(),
		'src'       => Constants::path( 'src/' ),
		'templates' => Constants::path( 'templates/' ),
		'languages' => Constants::path( 'languages/' ),
	],

	'urls'        => [
		'base' => Constants::pluginUrl(),
	],

	'cache'       => [
		'enabled' => true,
		'ttl'     => 3600,
		'prefix'  => 'allcoach_',
	],
];
