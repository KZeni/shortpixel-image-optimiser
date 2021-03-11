<?php
namespace ShortPixel\Controller;

use ShortPixel\ShortpixelLogger\ShortPixelLogger as Log;

class QuotaController
{
    protected static $instance;
    const CACHE_NAME = 'quotaData';

    protected $quotaData;

    public function __construct()
    {

    }

    public static function getInstance()
    {
      if (is_null(self::$instance))
        self::$instance = new QuotaController();

      return self::$instance;
    }

    public function hasQuota()
    {
      $settings = \wpSPIO()->settings();

      if ($settings->quotaExceeded)
        return false;

      return true;

    }

    protected function getQuotaData()
    {
        if (! is_null($this->quotaData))
          return $this->quotaData;

        $cache = new CacheController();

        $cacheData = $cache->getItem(self::CACHE_NAME);

        if (! $cacheData->exists() )
        {
            $quotaData = $this->getRemoteQuota();
            $cache->storeItem(self::CACHE_NAME, $quotaData, 6 * HOUR_IN_SECONDS);
        }
        else
          $quotaData = $cacheData->getValue();

        return $quotaData;
    }

    public function getQuota()
    {
          /*'quotaAvailable' => max(0, $quotaData['APICallsQuotaNumeric'] + $quotaData['APICallsQuotaOneTimeNumeric'] - $quotaData['APICallsMadeNumeric'] - $quotaData['APICallsMadeOneTimeNumeric']))); */
          $quotaData = $this->getQuotaData();
          $DateNow = time();
          $DateSubscription = strtotime($quotaData['APILastRenewalDate']);
          $DaysToReset = 30 - ((($DateNow  - $DateSubscription) / 84600) % 30);

          $quota = (object) [
              'monthly' => (object) [
                'text' => sprintf(__('%s/month', 'shortpixel-image-optimiser'), $quotaData['APICallsQuota']),
                'total' =>  $quotaData['APICallsQuotaNumeric'],
                'consumed' => $quotaData['APICallsMadeNumeric'],
                'remaining' => $quotaData['APICallsQuotaNumeric'] - $quotaData['APICallsMadeNumeric'],
                'renew' => $DaysToReset,
              ],
              'onetime' => (object) [
                'text' => $quotaData['APICallsQuotaOneTime'],
                'total' => $quotaData['APICallsQuotaOneTimeNumeric'],
                'consumed' => $quotaData['APICallsMadeOneTimeNumeric'],
                'remaining' => $quotaData['APICallsQuotaOneTimeNumeric'] - $quotaData['APICallsMadeOneTimeNumeric'],
              ],
          ];

          $quota->total = (object) [
              'total' => $quota->monthly->total + $quota->onetime->total,
              'consumed'  => $quota->monthly->consumed + $quota->onetime->consumed,
              'remaining' =>$quota->monthly->remaining + $quota->onetime->remaining,
          ];


          return $quota;
    }

    public function forceCheckRemoteQuota()
    {
       $this->getRemoteQuota();
    }

    private function resetQuotaExceeded()
    {
        $settings = \wpSPIO()->settings();
        if( $settings->quotaExceeded == 1) {
            $dismissed = $settings->dismissedNotices ? $settings->dismissedNotices : array();
            //unset($dismissed['exceed']);
            $settings->prioritySkip = array();
            $settings->dismissedNotices = $dismissed;
            \ShortPixel\Controller\adminNoticesController::resetAPINotices();
            \ShortPixel\Controller\adminNoticesController::resetQuotaNotices();
        }
        $settings->quotaExceeded = 0;
    }

    public function remoteValidateKey($key)
    {
        return $this->getRemoteQuota($key, true);
    }


    private function getRemoteQuota($apiKey = false, $validate = false)
    {
        if (! $apiKey && ! $validate) // validation is done by apikeymodel, might result in a loop.
        {
          $keyControl = ApiKeyController::getInstance();
          $apiKey = $keyControl->forceGetApiKey();
        }
        if(is_null($apiKey)) {
          $apiKey = $settings->apiKey;
        }

        $settings = \wpSPIO()->settings();

          if($settings->httpProto != 'https' && $settings->httpProto != 'http') {
              $settings->httpProto = 'https';
          }

          $requestURL = $settings->httpProto . '://' . SHORTPIXEL_API . '/v2/api-status.php';
          $args = array(
              'timeout'=> SHORTPIXEL_VALIDATE_MAX_TIMEOUT,
              'body' => array('key' => $apiKey)
          );
          $argsStr = "?key=".$apiKey;

          //if($appendUserAgent) { // See no reason why not(?)
              $args['body']['useragent'] = "Agent" . urlencode($_SERVER['HTTP_USER_AGENT']);
              $argsStr .= "&useragent=Agent".$args['body']['useragent'];
          //}

          $args['body']['host'] = parse_url(get_site_url(),PHP_URL_HOST);
          $argsStr .= "&host={$args['body']['host']}";
          if(strlen($settings->siteAuthUser)) {

              $args['body']['user'] = stripslashes($settings->siteAuthUser);
              $args['body']['pass'] = stripslashes($settings->siteAuthPass);
              $argsStr .= '&user=' . urlencode($args['body']['user']) . '&pass=' . urlencode($args['body']['pass']);
          }
          if($settings !== false) {
              $args['body']['Settings'] = $settings;
          }

          $time = microtime(true);
          $comm = array();

          //Try first HTTPS post. add the sslverify = false if https
          if($settings->httpProto === 'https') {
              $args['sslverify'] = false;
          }
          $response = wp_remote_post($requestURL, $args);

          $comm['A: ' . (number_format(microtime(true) - $time, 2))] = array("sent" => "POST: " . $requestURL, "args" => $args, "received" => $response);

          //some hosting providers won't allow https:// POST connections so we try http:// as well
          if(is_wp_error( $response )) {

              $requestURL = $settings->httpProto == 'https' ?
                  str_replace('https://', 'http://', $requestURL) :
                  str_replace('http://', 'https://', $requestURL);
              // add or remove the sslverify
              if($settings->httpProto === 'http') {
                  $args['sslverify'] = false;
              } else {
                  unset($args['sslverify']);
              }
              $response = wp_remote_post($requestURL, $args);
              $comm['B: ' . (number_format(microtime(true) - $time, 2))] = array("sent" => "POST: " . $requestURL, "args" => $args, "received" => $response);

              if(!is_wp_error( $response )){
                  $settings->httpProto = ($settings->httpProto == 'https' ? 'http' : 'https');
              } else {
              }
          }
          //Second fallback to HTTP get
          if(is_wp_error( $response )){
              $args['body'] = null;
              $requestURL .= $argsStr;
              $response = wp_remote_get($requestURL, $args);
              $comm['C: ' . (number_format(microtime(true) - $time, 2))] = array("sent" => "POST: " . $requestURL, "args" => $args, "received" => $response);
          }
          Log::addInfo("API STATUS COMM: " . json_encode($comm));

          $defaultData = array(
              "APIKeyValid" => false,
              "Message" => __('API Key could not be validated due to a connectivity error.<BR>Your firewall may be blocking us. Please contact your hosting provider and ask them to allow connections from your site to api.shortpixel.com (IP 176.9.21.94).<BR> If you still cannot validate your API Key after this, please <a href="https://shortpixel.com/contact" target="_blank">contact us</a> and we will try to help. ','shortpixel-image-optimiser'),
              "APICallsMade" => __('Information unavailable. Please check your API key.','shortpixel-image-optimiser'),
              "APICallsQuota" => __('Information unavailable. Please check your API key.','shortpixel-image-optimiser'),
              "APICallsMadeOneTime" => 0,
              "APICallsQuotaOneTime" => 0,
              "APICallsMadeNumeric" => 0,
              "APICallsQuotaNumeric" => 0,
              "APICallsMadeOneTimeNumeric" => 0,
              "APICallsQuotaOneTimeNumeric" => 0,
              "APICallsRemaining" => 0,
              "APILastRenewalDate" => 0,
              "DomainCheck" => 'NOT Accessible');
          $defaultData = is_array($settings->currentStats) ? array_merge( $settings->currentStats, $defaultData) : $defaultData;

          if(is_object($response) && get_class($response) == 'WP_Error') {

              $urlElements = parse_url($requestURL);
              $portConnect = @fsockopen($urlElements['host'],8,$errno,$errstr,15);
              if(!$portConnect) {
                  $defaultData['Message'] .= "<BR>Debug info: <i>$errstr</i>";
              }
              return $defaultData;
          }

          if($response['response']['code'] != 200) {
             return $defaultData;
          }

          $data = $response['body'];
          $data = json_decode($data);

          if(empty($data)) { return $defaultData; }

          if($data->Status->Code != 2) {
              $defaultData['Message'] = $data->Status->Message;
              return $defaultData;
          }

          if ( ( $data->APICallsMade + $data->APICallsMadeOneTime ) < ( $data->APICallsQuota + $data->APICallsQuotaOneTime ) ) //reset quota exceeded flag -> user is allowed to process more images.
              $this->resetQuotaExceeded();
          else
              $settings->quotaExceeded = 1;//activate quota limiting

          $dataArray = array(
              "APIKeyValid" => true,
              "APICallsMade" => number_format($data->APICallsMade) . __(' images','shortpixel-image-optimiser'),
              "APICallsQuota" => number_format($data->APICallsQuota) . __(' images','shortpixel-image-optimiser'),
              "APICallsMadeOneTime" => number_format($data->APICallsMadeOneTime) . __(' images','shortpixel-image-optimiser'),
              "APICallsQuotaOneTime" => number_format($data->APICallsQuotaOneTime) . __(' images','shortpixel-image-optimiser'),
              "APICallsMadeNumeric" => (int) $data->APICallsMade,
              "APICallsQuotaNumeric" => (int) $data->APICallsQuota,
              "APICallsMadeOneTimeNumeric" =>  (int) $data->APICallsMadeOneTime,
              "APICallsQuotaOneTimeNumeric" => (int) $data->APICallsQuotaOneTime,
              "APICallsRemaining" => $data->APICallsQuota + $data->APICallsQuotaOneTime - $data->APICallsMade - $data->APICallsMadeOneTime,
              "APILastRenewalDate" => $data->DateSubscription,
              "DomainCheck" => (isset($data->DomainCheck) ? $data->DomainCheck : null)
          );

          // Why is this?

          Log::addDebug('GetQuotaInformation Result ', $dataArray);
          return $dataArray;
    }

}
