<?php

namespace Arhx\ProxyChecker;

class ProxyChecker {
	private $proxyCheckUrl;

	private $options = [
		'timeout' => 10,
		'check'  => [ 'get', 'post', 'cookie', 'referer', 'user_agent' ],
	];

	public function __construct( $check_page, array $options = [] ) {
		$this->proxyCheckUrl = $check_page;
		$this->setOptions( $options );
	}

	public function setOptions( array $options ) {
		$this->options = $options + $this->options;
	}
	public function getOption($key, $default = null){
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}

	/**
	 * @param array $proxies
	 * @param array $errors
	 *
	 * @return array
	 */
	public function checkMultipleProxy( array $proxies, &$errors = []) {

		$multi = curl_multi_init();
		$channels = array();

// Loop through the URLs, create curl-handles
// and attach the handles to our multi-request
		foreach ($proxies as $i => $proxy) {
			$ch = $this->makeCurlHandler($proxy);

			curl_multi_add_handle($multi, $ch);

			$channels[$i] = $ch;
		}

// While we're still active, execute curl
		do {
			$status = curl_multi_exec($multi, $active);
			if ($active) {
				// Ждем какое-то время для оживления активности
				curl_multi_select($multi);
			}
		} while ($active && $status == CURLM_OK);

// Loop through the channels and retrieve the received
// content, then remove the handle from the multi-handle
		$results = [];
		foreach ($channels as $i => $channel) {
			$content = curl_multi_getcontent($channel);
			try{
				$results[$i] = $this->checkProxyContent($content);
			}catch (\Exception $exception){
				$results[$i] = false;
				$errors[$i] = $exception->getMessage();
			}
			curl_multi_remove_handle($multi, $channel);
		}
// Close the multi-handle and return our results
		curl_multi_close($multi);
		return $results;
	}

	/**
	 * @param $proxy
	 * @param $error_message
	 *
	 * @return array|bool
	 */
	public function checkProxy( $proxy, &$error_message = null ) {
		$error_message = null;
		try{
			$response = $this->getProxyContent( $proxy );
			$result = $this->checkProxyContent( $response );
			return $result;
		}catch (\Exception $exception){
			$error_message = $exception->getMessage();
			return false;
		}
	}

	private function getProxyContent( $proxy ) {
		$ch = $this->makeCurlHandler($proxy);

		$body = curl_exec($ch);
		//$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		curl_close($ch);
		return $body;
	}
	private function makeCurlHandler($proxy){
		$timeout = $this->getOption('timeout', 30);
		$check = $this->getOption('check', []);

		$url = $this->proxyCheckUrl;
		// check get
		if ( in_array( 'get', $check ) ) {
			$url .= '?q=query';
		}
		$ch = curl_init ($url);
		if($proxy){
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
		}

		$headers = [];
		// check referer
		if ( in_array( 'referer', $check ) ) {
			$headers[] = "Referer: http://www.google.com/";
		}

		// check user_agent
		if ( in_array( 'user_agent', $check ) ) {
			$headers[] = "User-Agent: Mozilla/5.0";
		}
		// check cookie
		if ( in_array( 'cookie', $check ) ) {
			$headers[] = "Cookie: c=cookie";
		}
		if(count($headers) > 0){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

		// check post
		if ( in_array( 'post', $check ) ) {
			curl_setopt ($ch, CURLOPT_POST, 1);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query([
				'r' => 'request'
			]));
		}

		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		return $ch;
	}

	/**
	 * @param $content
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function checkProxyContent( $content ) {
		if ( ! $content ) {
			throw new \Exception( 'Empty content' );
		}
		if ( strpos( $content, 'check this string in proxy response content' ) === false ) {
			throw new \Exception( 'Wrong content' );
		}

		$allowed    = [];
		$disallowed = [];
		foreach ( $this->getOption('check',[]) as $value ) {
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