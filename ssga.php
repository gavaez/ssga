<?php

/**
 * Simple Server Side Google Analytics
 *
 * @author nikitin82@gmail.com
 *
 * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/
 */
final class SSGA
{
	const GA_COOKIE  = '_ga';
	const GA_VERSION = '1.2';

	/**
	 * @var mixed[]
	 */
	private $params = [];

	/**
	 * @var string
	 */
	private $tid;


	/**
	 * @param string $tid tracking identifier
	 */
	public function __construct($tid)
	{
		$this->reset($tid);
	}

	/**
	 * @param string $tid tracking identifier [optional]
	 */
	public function reset($tid = null)
	{
		if ($tid !== null) {
			$this->tid = $tid;
		}

		if (array_key_exists(self::GA_COOKIE, $_COOKIE)) {
			@list(,, $cid) = explode('.', $_COOKIE[self::GA_COOKIE], 3);
		}
		if (empty($cid)) {
			$cid = sprintf(
				'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0x0fff) | 0x4000,
				mt_rand(0, 0x3fff) | 0x8000,
				mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0xffff)
			);
			$_COOKIE[self::GA_COOKIE] = sprintf('GA%s.%s', self::GA_VERSION, $cid);
		}

		$this->params = [
			'v'   => 1,
			'tid' => $this->tid,
			'cid' => $cid,
		];
	}

	/**
	 * @param string $category
	 * @param string $action
	 * 
	 * @return bool
	 */
	public function sendEvent($category, $action)
	{
		$this->setParams([
			't' => 'event',
			'ec' => $category,
			'ea' => $action,
		]);

		return $this->send();
	}

	/**
	 * @param integer $ti transaction identifier
	 *
	 * @return bool
	 */
	public function refundTransaction($ti)
	{
		$this->setParams([
			'ni' => 1, // non interaction
			'ti' => $ti,
			'pa' => 'refund',
		]);

		return $this->sendEvent('Ecommerce', 'Refund');
	}

	/**
	 * @return bool
	 */
	public function send()
	{
		$ch = curl_init('http://www.google-analytics.com/collect');
		if (!$ch) {
			return null;
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($this->params),
		]);
		curl_exec($ch);
		$success = preg_match('#^2\d{2}$#', curl_getinfo($ch, CURLINFO_HTTP_CODE));
		curl_close($ch);

		return (bool)$success;
	}

	/**
	 * PHP magic getter
	 *
	 * @param string $name
	 * 
	 * @return mixed
	 */
	public function __get($name)
	{
		return isset($this->$name) ? $this->params[$name] : null;
	}

	/**
	 * PHP magic setter
	 *
	 * @param string $name
	 * @param mixed $value
	 * 
	 * @throws RuntimeException
	 */
	public function __set($name, $value)
	{
		if (!strcasecmp($name, 'tid')) {
			throw new RuntimeException('Property "tid" is read only');
		}
		$this->params[$name] = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset($name)
	{
		return array_key_exists($name, $this->params);
	}

	/**
	 * @param mixed[] $params
	 */
	private function setParams(array $params)
	{
		$this->params = $params + $this->params;
	}
}
