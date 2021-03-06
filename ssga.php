<?php

/**
 * Simple Server Side Google Analytics
 *
 * @author nikitin82@gmail.com
 *
 * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/
 *
 * @property-read string $_ga cookie value
 * @property-read string $tid tracking identifier
 * @property      int    $uid user identifier
 */
class SSGA
{
    const GA_COOKIE  = '_ga';
    const GA_VERSION = 1;

    // Checkout steps
    const CHECKOUT_PAYMENT  = 1;
    const CHECKOUT_DELIVERY = 2;

    /**
     * @var mixed[]
     */
    private $params = [];

    /**
     * @var string
     */
    private $tid;

    /**
     * @var int
     */
    private $timeout;


    /**
     * @param string $tid     tracking identifier
     * @param int    $timeout [optional]
     */
    public function __construct($tid, $timeout = null)
    {
        $this->reset($tid);

        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public static function getClientId()
    {
        if (array_key_exists(self::GA_COOKIE, $_COOKIE)) {
            @list(,, $cid) = explode('.', $_COOKIE[self::GA_COOKIE], 4);
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
            $_COOKIE[self::GA_COOKIE] = sprintf(
                'GA%u.%u.%s',
                self::GA_VERSION,
                (array_key_exists('HTTP_HOST', $_SERVER) ? count_chars($_SERVER['HTTP_HOST'])[ord('.')] : 0) + 1,
                $cid
            );
        }

        return $cid;
    }

    /**
     * @param string $tid tracking identifier [optional]
     */
    public function reset($tid = null)
    {
        if ($tid !== null) {
            $this->tid = $tid;
        }

        $this->params = ['v' => 1, 'tid' => $this->tid, 'cid' => static::getClientId()];
    }

    /**
     * @param mixed[] $params [optional]
     *
     * @return bool
     */
    public function send(array $params = [])
    {
        $ch = curl_init('http://www.google-analytics.com/collect');
        if (!$ch) {
            return null;
        }

        /** @noinspection AdditionOperationOnArraysInspection */
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(array_filter($params, function ($param) {
                return $param !== null;
            }) + $this->params),
        ];
        if (isset($this->timeout)) {
            $options[CURLOPT_TIMEOUT] = $this->timeout;
        }

        curl_setopt_array($ch, $options);
        curl_exec($ch);
        $success = preg_match('#^2\d{2}$#', curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        return (bool) $success;
    }

    /**
     * @param mixed[] $params [optional]
     *
     * @return bool
     */
    public function sendView(array $params = [])
    {
        return $this->send([
            't'  => 'pageview',
            'dh' => $_SERVER['HTTP_HOST'],
            'dp' => $_SERVER['REQUEST_URI'],
        ] + $params);
    }

    /**
     * @param string  $category
     * @param string  $action
     * @param mixed[] $params [optional]
     *
     * @return bool
     */
    public function sendEvent($category, $action, array $params = [])
    {
        return $this->send(['t' => 'event', 'ec' => $category, 'ea' => $action] + $params);
    }

    /**
     * @param GA\productFieldObject[] $items
     * @param int                     $step
     * @param string                  $value
     * @param mixed[]                 $params [optional]
     *
     * @return bool
     */
    public function checkout(array $items, $step, $value, array $params = [])
    {
        return ($pParams = $this->productsToParams($items))
            && $this->sendView(['cos' => $step, 'col' => $value] + $pParams + $params);
    }

    /**
     * @param GA\productFieldObject[] $items
     * @param int                     $ti          transaction identifier
     * @param string                  $affiliation
     * @param float                   $revenue
     * @param float                   $shipping    [optional]
     * @param float                   $tax         [optional]
     * @param string                  $coupon      [optional]
     * @param mixed[]                 $params      [optional]
     *
     * @return bool
     */
    public function purchase(
        array $items,
        $ti,
        $affiliation,
        $revenue,
        $shipping = null,
        $tax = null,
        $coupon = null,
        array $params = []
    ) {
        return ($pParams = $this->productsToParams($items)) && $this->sendView([
            'pa'  => 'purchase',
            'ti'  => $ti,
            'ta'  => $affiliation,
            'tr'  => $revenue,
            'ts'  => $shipping,
            'tt'  => $tax,
            'tcc' => $coupon,
        ] + $pParams + $params);
    }

    /**
     * @param int     $ti     transaction identifier
     * @param mixed[] $params [optional]
     *
     * @return bool
     */
    public function refund($ti, array $params = [])
    {
        return $this->sendEvent('Ecommerce', 'Refund', [
            'ni' => 1, // non interaction
            'ti' => $ti,
            'pa' => 'refund',
        ] + $params);
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
        if (!strcasecmp($name, '_ga')) {
            static::getClientId();
            return $_COOKIE[self::GA_COOKIE];
        }

        return isset($this->$name) ? $this->params[$name] : null;
    }

    /**
     * PHP magic setter
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \RuntimeException
     */
    public function __set($name, $value)
    {
        if (!strcasecmp($name, 'tid')) {
            throw new \RuntimeException('Property "tid" is read only');
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
     * @param GA\productFieldObject[] $items
     *
     * @return mixed[]
     */
    private function productsToParams(array $items)
    {
        static $map = [
            'id' => 'id',
            'nm' => 'name',
            'ca' => 'category',
            'br' => 'brand',
            'va' => 'variant',
            'pr' => 'price',
            'qt' => 'quantity',
        ];

        $params = [];
        foreach ($items as $item) {
            if ($item instanceof GA\productFieldObject) {
                foreach ($map as $param => $attr) {
                    $params["pr$item->position$param"] = $item->$attr;
                }
            }
        }

        return $params;
    }
}
