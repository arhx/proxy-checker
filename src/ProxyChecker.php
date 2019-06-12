<?php

namespace Arhx\ProxyChecker;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;

class ProxyChecker {
	private $proxyCheckUrl;

	private $config = [
		'guzzle' => [
			'connect_timeout' => 5,
			'read_timeout'    => 10,
		],
		'check'  => [ 'get', 'post', 'cookie', 'referer', 'user_agent' ],
	];

	public function __construct( $check_page, array $config = [] ) {
		$this->proxyCheckUrl = $check_page;
		$this->setConfig( $config );
	}

	public function setConfig( array $config ) {
		$this->config = array_merge( $this->config, $config );
	}

	/**
	 * @param $proxy
	 * @param $error_message
	 *
	 * @return array|bool
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function checkProxy( $proxy, &$error_message ) {
		try{
			$response = $this->getProxyContent( $proxy );
			$result = $this->checkProxyContent( $response );
			return $result;
		}catch (\Exception $exception){
			$error_message = $exception->getMessage();
			return false;
		}
	}

	/**
	 * @param $proxy
	 *
	 * @return Response
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function getProxyContent( $proxy ) {
		$guzzleConfig          = $this->config['guzzle'];
		if($proxy){
			$guzzleConfig['proxy'] = $proxy;
		}
		if ( ! isset( $guzzleConfig['headers'] ) ) {
			$guzzleConfig['headers'] = [];
		}


		// check cookie
		if ( in_array( 'cookie', $this->config['check'] ) ) {
			$domain = parse_url( $this->proxyCheckUrl, PHP_URL_HOST );

			$cookieJar = CookieJar::fromArray( [
				'c' => 'cookie'
			], $domain );

			$guzzleConfig['cookies'] = $cookieJar;
		}

		$url = $this->proxyCheckUrl;
		$method = 'GET';
		$requestOptions = [];

		// check get
		if ( in_array( 'get', $this->config['check'] ) ) {
			$url .= '?q=query';
		}

		// check post
		if ( in_array( 'post', $this->config['check'] ) ) {
			$method = 'POST';
			$requestOptions['form_params'] = [
					'r' => 'request',
				];
		}
		// check referer
		if ( in_array( 'referer', $this->config['check'] ) ) {
			$guzzleConfig['headers']['Referer'] = 'http://www.google.com/';
		}

		// check user_agent
		if ( in_array( 'user_agent', $this->config['check'] ) ) {
			$guzzleConfig['headers']['User-Agent'] = 'Mozilla/5.0';
		}
		$client = new Client( $guzzleConfig );

		return $client->request( $method, $url, $requestOptions );
	}

	/**
	 * @param Response $response
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function checkProxyContent( Response $response ) {
		$content = $response->getBody()->getContents();
		if ( ! $content ) {
			throw new \Exception( 'Empty content' );
		}
		if ( strpos( $content, 'check this string in proxy response content' ) === false ) {
			throw new \Exception( 'Wrong content' );
		}

		if ( $response->getStatusCode() != 200 ) {
			throw new \Exception( 'Code invalid: ' . $response->getStatusCode() );
		}

		$allowed    = [];
		$disallowed = [];
		foreach ( $this->config['check'] as $value ) {
			if ( strpos( $content, "allow_$value" ) !== false ) {
				$allowed[] = $value;
			} else {
				$disallowed[] = $value;
			}
		}

		// proxy level
		$proxyLevel = '';
		if ( strpos( $content, 'proxylevel_elite' ) !== false ) {
			$proxyLevel = 'elite';
		} elseif ( strpos( $content, 'proxylevel_anonymous' ) !== false ) {
			$proxyLevel = 'anonymous';
		} elseif ( strpos( $content, 'proxylevel_transparent' ) !== false ) {
			$proxyLevel = 'transparent';
		}

		return [
			'allowed'     => $allowed,
			'disallowed'  => $disallowed,
			'proxy_level' => $proxyLevel,
		];
	}
	static public function checkPage(){

		$result = [];
		$result[] = 'check this string in proxy response content';

		if (!empty($_GET['q']) && ('query' == $_GET['q'])) {
			$result[] = 'allow_get';
		}
		if (!empty($_POST['r']) && ('request' == $_POST['r'])) {
			$result[] = 'allow_post';
		}
		if (!empty($_COOKIE['c']) && ('cookie' == $_COOKIE['c'])) {
			$result[] = 'allow_cookie';
		}
		if (!empty($_SERVER['HTTP_REFERER']) && ('http://www.google.com/' == $_SERVER['HTTP_REFERER'])) {
			$result[] = 'allow_referer';
		}
		if (!empty($_SERVER['HTTP_USER_AGENT']) && ('Mozilla/5.0' == $_SERVER['HTTP_USER_AGENT'])) {
			$result[] = 'allow_user_agent';
		}
		//proxy levels
		//Level 3 Elite Proxy, connection looks like a regular client
		//Level 2 Anonymous Proxy, no ip is forworded but target site could still tell it's a proxy
		//Level 1 Transparent Proxy, ip is forworded and target site would be able to tell it's a proxy
		if(!isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !isset($_SERVER['HTTP_VIA']) && !isset($_SERVER['HTTP_PROXY_CONNECTION'])) {
			$result[] = 'proxylevel_elite';
		} elseif(!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$result[] = 'proxylevel_anonymous';
		} else {
			$result[] = 'proxylevel_transparent';
		}
		return $result;
	}
}