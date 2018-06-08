<?php

use function \EE\Utils\mustache_render;

class Site_Docker {

	/**
	 * Generate docker-compose.yml according to requirement.
	 *
	 * @param array $filters Array of flags to determine the docker-compose.yml generation.
	 *                       Empty/Default -> Generates default WordPress docker-compose.yml
	 *                       ['le']        -> Enables letsencrypt in the generation.
	 *
	 * @return String docker-compose.yml content string.
	 */
	public function generate_docker_compose_yml( array $filters = [] ) {
		$base            = array();
		$restart_default = array( 'name' => 'always' );
		$network_default = array( 'name' => 'site-network' );
		// db configuration.
		$db['service_name'] = array( 'name' => 'db' );
		$db['image']        = array( 'name' => 'easyengine/mariadb' );
		$db['restart']      = $restart_default;
		$db['volumes']      = array( array( 'vol' => array( 'name' => './app/db:/var/lib/mysql' ) ) );
		$db['environment']  = array(
			'env' => array(
				array( 'name' => 'MYSQL_ROOT_PASSWORD' ),
				array( 'name' => 'MYSQL_DATABASE' ),
				array( 'name' => 'MYSQL_USER' ),
				array( 'name' => 'MYSQL_PASSWORD' ),
			),
		);
		$db['networks']     = $network_default;
		// PHP configuration.
		$php['service_name'] = array( 'name' => 'php' );
		$php['image']        = array( 'name' => 'easyengine/php' );
		$php['depends_on']   = array( 'name' => 'db' );
		$php['restart']      = $restart_default;
		$php['volumes']      = array( array( 'vol' => array( array( 'name' => './app/src:/var/www/html' ), array( 'name' => './config/php-fpm/php.ini:/usr/local/etc/php/php.ini' ) ) ) );
		$php['environment']  = array(
			'env' => array(
				array( 'name' => 'WORDPRESS_DB_HOST' ),
				array( 'name' => 'WORDPRESS_DB_USER=${MYSQL_USER}' ),
				array( 'name' => 'WORDPRESS_DB_PASSWORD=${MYSQL_PASSWORD}' ),
				array( 'name' => 'USER_ID=${USER_ID}' ),
				array( 'name' => 'GROUP_ID=${GROUP_ID}' ),
			),
		);
		$php['networks']     = $network_default;
		// nginx configuration..
		$nginx['service_name'] = array( 'name' => 'nginx' );
		$nginx['image']        = array( 'name' => 'easyengine/nginx' );
		$nginx['depends_on']   = array( 'name' => 'php' );
		$nginx['restart']      = $restart_default;
		$v_host                = in_array( 'wpsubdom', $filters ) ? 'VIRTUAL_HOST=${VIRTUAL_HOST},HostRegexp:{subdomain:.+}.${VIRTUAL_HOST}' : 'VIRTUAL_HOST';
		if ( in_array( 'le', $filters ) ) {
			$le_v_host            = in_array( 'wpsubdom', $filters ) ? 'LETSENCRYPT_HOST=${VIRTUAL_HOST},HostRegexp:{subdomain:.+}.${VIRTUAL_HOST}' : 'LETSENCRYPT_HOST=${VIRTUAL_HOST}';
			$nginx['environment'] = array( 'env' => array( array( 'name' => $v_host ), array( 'name' => $le_v_host ), array( 'name' => 'LETSENCRYPT_EMAIL=${VIRTUAL_HOST_EMAIL}' ) ) );
		} else {
			$nginx['environment'] = array( 'env' => array( array( 'name' => $v_host ) ) );
		}
		$nginx['volumes']  = array( array( 'vol' => array( array( 'name' => './app/src:/var/www/html' ), array( 'name' => './config/nginx/default.conf:/etc/nginx/conf.d/default.conf' ), array( 'name' => './logs/nginx:/var/log/nginx' ), array( 'name' => './config/nginx/common:/usr/local/openresty/nginx/conf/common' ) ) ) );
		$nginx['networks'] = $network_default;
		// PhpMyAdmin configuration.
		$phpmyadmin['service_name'] = array( 'name' => 'phpmyadmin' );
		$phpmyadmin['image']        = array( 'name' => 'easyengine/phpmyadmin' );
		$phpmyadmin['restart']      = $restart_default;
		$phpmyadmin['environment']  = array( 'env' => array( array( 'name' => 'VIRTUAL_HOST=pma.${VIRTUAL_HOST}' ) ) );
		$phpmyadmin['networks']     = $network_default;
		// mailhog configuration.
		$mail['service_name'] = array( 'name' => 'mail' );
		$mail['image']        = array( 'name' => 'easyengine/mail' );
		$mail['restart']      = $restart_default;
		$mail['command']      = array( 'name' => '["-invite-jim=false"]' );
		$mail['environment']  = array( 'env' => array( array( 'name' => 'VIRTUAL_HOST=mail.${VIRTUAL_HOST}' ), array( 'name' => 'VIRTUAL_PORT=8025' ) ) );
		$mail['networks']     = $network_default;

		// redis configuration.
		$redis['service_name'] = array( 'name' => 'redis' );
		$redis['image']        = array( 'name' => 'easyengine/redis' );
		$redis['networks']     = $network_default;

		if ( in_array( 'wpredis', $filters, true ) ) {
			$base[] = $redis;
		}

		$base[]  = $db;
		$base[]  = $php;
		$base[]  = $nginx;
		$base[]  = $mail;
		$base[]  = $phpmyadmin;
		$binding = array(
			'services' => $base,
			'network'  => true,
		);

		$docker_compose_yml = mustache_render( 'vendor/easyengine/site-command/templates/docker-compose.mustache', $binding );

		return $docker_compose_yml;
	}
}