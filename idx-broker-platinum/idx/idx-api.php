<?php
namespace IDX;

class Idx_Api
{
    public function __construct()
    {
        $this->api_key = get_option('idx_broker_apikey');
    }

    public $api_key;
    /**
     * apiResponse handles the various replies we get from the IDX Broker API and returns appropriate error messages.
     * @param  [array] $response [response header from API call]
     * @return [array]           [keys: 'code' => response code, 'error' => false (default), or error message if one is found]
     */
    public function apiResponse($response)
    {
        if (!$response || !is_array($response) || !isset($response['response'])) {
            return array("code" => "Generic", "error" => "Unable to complete API call.");
        }
        if (!function_exists('curl_init')) {
            return array("code" => "PHP", "error" => "The cURL extension for PHP is not enabled on your server.<br />Please contact your developer and/or hosting provider.");
        }
        $response_code = $response['response']['code'];
        $err_message = false;
        if (is_numeric($response_code)) {
            switch ($response_code) {
                case 401:$err_message = 'Access key is invalid or has been revoked, please ensure there are no spaces in your key.<br />If the problem persists, please reset your API key in the IDX Broker Platinum Dashboard or call 800-421-9668.';
                    break;
                case 403:
                case 403.4:$err_message = 'API call generated from WordPress is not using SSL (HTTPS) to communicate.<br />Please contact your developer and/or hosting provider.';
                    break;
                case 405:
                case 409:$err_message = 'Invalid request sent to IDX Broker API, please re-install the IDX Broker Platinum plugin';
                    break;
                case 406:$err_message = 'Access key is missing. To obtain an access key, please visit your IDX Broker Platinum Dashboard';
                    break;
                case 412:$err_message = 'Your account has exceeded the hourly access limit for your API key.<br />You may either wait and try again later, reset your API key in the IDX Broker Platinum Dashboard, or call 800-421-9668.';
                    break;
                case 500:$err_message = 'General system error when attempting to communicate with the IDX Broker API, please try again in a few moments or contact 800-421-9668 if the problem persists.';
                    break;
                case 503:$err_message = 'IDX Broker API is currently undergoing maintenance. Please try again in a few moments or call 800-421-9668 if the problem persists.';
                    break;
            }
        }
        return array("code" => $response_code, "error" => $err_message);
    }

    /**
     * IDX API Request
     */
    public function idx_api(
        $method,
        $apiversion = Initiate_Plugin::IDX_API_DEFAULT_VERSION,
        $level = 'clients',
        $params = array(),
        $expiration = 7200,
        $request_type = 'GET'
    ) {
        $cache_key = 'idx_' . $method . '_cache';
        if (($data = $this->get_transient($cache_key))) {
            return $data;
        }

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'accesskey' => $this->api_key,
            'outputtype' => 'json',
            'apiversion' => $apiversion,
            'pluginversion' => \Idx_Broker_Plugin::IDX_WP_PLUGIN_VERSION,
        );

        $params = array_merge(array('timeout' => 120, 'sslverify' => false, 'headers' => $headers), $params);
        $url = Initiate_Plugin::IDX_API_URL . '/' . $level . '/' . $method;

        if ($request_type === 'POST') {
            $response = wp_safe_remote_post($url, $params);
        } else {
            $response = wp_remote_get($url, $params);
        }
        $response = (array) $response;

        extract($this->apiResponse($response)); // get code and error message if any, assigned to vars $code and $error
        if (isset($error) && $error !== false) {
            if ($code == 401) {
                $this->delete_transient($cache_key);
            }
            return new \WP_Error("idx_api_error", __("Error {$code}: $error"));
        } else {
            $data = (array) json_decode((string) $response['body']);
            $this->set_transient($cache_key, $data, $expiration);
            return $data;
        }
    }

    /*
     * If option does not exist or timestamp is old, return false.
     * Otherwise return data
     * We create our own transient functions to avoid bugs with the object cache
     * for caching plugins.
     */
    public function get_transient($name)
    {
        $data = get_option($name);
        if (empty($data)) {
            return false;
        }
        $data = unserialize($data);
        $expiration = $data['expiration'];
        if ($expiration < time()) {
            return false;
        }
        return $data['data'];
    }

    public function set_transient($name, $data, $expiration)
    {
        $expiration = time() + $expiration;
        $data = array(
            'data' => $data,
            'expiration' => $expiration,
        );
        $data = serialize($data);
        update_option($name, $data);
    }

    public function delete_transient($name)
    {
        delete_option($name);
    }

    /**
     * Clean IDX cached data
     *
     * @param void
     * @return void
     */
    public function idx_clean_transients()
    {
        if ($this->get_transient('idx_savedlinks_cache')) {
            $this->delete_transient('idx_savedlinks_cache');
        }
        if ($this->get_transient('idx_widgetsrc_cache')) {
            $this->delete_transient('idx_widgetsrc_cache');
        }
        if ($this->get_transient('idx_systemlinks_cache')) {
            $this->delete_transient('idx_systemlinks_cache');
        }
        $this->clear_wrapper_cache();
    }

    /**
     *
     * Using our web services function, lets get the system links built in the middleware,
     * clean and prepare them, and return them in a new array for use.
     *
     */
    public function idx_api_get_systemlinks()
    {
        if (!$this->api_key) {
            return array();
        }
        return $this->idx_api('systemlinks', $this->idx_api_get_apiversion());
    }

    /**
     *
     * Using our web services function, lets get saved links built in the middleware,
     * clean and prepare them, and return them in a new array for use.
     *
     */
    public function idx_api_get_savedlinks()
    {
        if (!$this->api_key) {
            return array();
        }
        return $this->idx_api('savedlinks', $this->idx_api_get_apiversion());
    }

    /**
     *
     * Using our web services function, lets get the widget details built in the middleware,
     * clean and prepare them, and return them in a new array for use.
     *
     */
    public function idx_api_get_widgetsrc()
    {
        if (!$this->api_key) {
            return array();
        }
        return $this->idx_api('widgetsrc', $this->idx_api_get_apiversion());
    }

    /**
     * Get api version
     */
    public function idx_api_get_apiversion()
    {
        if (!$this->api_key) {
            return Initiate_Plugin::IDX_API_DEFAULT_VERSION;
        }

        $data = $this->idx_api('apiversion', Initiate_Plugin::IDX_API_DEFAULT_VERSION, 'clients', array(), 86400);
        if (is_array($data) && !empty($data)) {
            return $data['version'];
        } else {
            return Initiate_Plugin::IDX_API_DEFAULT_VERSION;
        }
    }

    public function system_results_url()
    {

        $links = $this->idx_api_get_systemlinks();

        if (!$links) {
            return false;
        }

        foreach ($links as $link) {
            if ($link->systemresults) {
                $results_url = $link->url;
            }
        }

        // What if or can they have more than one system results page?
        if (isset($results_url)) {
            return $results_url;
        }

        return false;
    }

    /**
     * Returns the url of the link
     *
     * @param string $name name of the link to return the url of
     * @return bool|string
     */
    public function system_link_url($name)
    {

        $links = $this->idx_api_get_systemlinks();

        if (!$links) {
            return false;
        }

        foreach ($links as $link) {
            if ($name == $link->name) {
                return $link->url;
            }
        }

        return false;
    }

    /**
     * Returns the url of the first system link found with
     * a category of "details"
     *
     * @return bool|string link url if found else false
     */
    public function details_url()
    {

        $links = $this->idx_api_get_systemlinks();

        if (!$links) {
            return false;
        }

        foreach ($links as $link) {
            if ('details' == $link->category) {
                return $link->url;
            }
        }

        return false;
    }

    /**
     * Returns an array of system link urls
     *
     * @return array
     */
    public function all_system_link_urls()
    {

        $links = $this->idx_api_get_systemlinks();

        if (!$links) {
            return array();
        }

        $system_link_urls = array();

        foreach ($links as $link) {
            $system_link_urls[] = $link->url;
        }

        return $system_link_urls;
    }

    /**
     * Returns an array of system link names
     *
     * @return array
     */
    public function all_system_link_names()
    {

        $links = $this->idx_api_get_systemlinks();

        if (!$links) {
            return array();
        }

        $system_link_names = array();

        foreach ($links as $link) {
            $system_link_names[] = $link->name;
        }

        return $system_link_names;
    }

    public function all_saved_link_urls()
    {

        $links = $this->idx_api_get_savedlinks();

        if (!$links) {
            return array();
        }

        $system_link_urls = array();

        foreach ($links as $link) {
            $system_link_urls[] = $link->url;
        }

        return $system_link_urls;
    }

    public function all_saved_link_names()
    {

        $links = $this->idx_api_get_savedlinks();

        if (!$links) {
            return array();
        }

        $system_link_names = array();

        foreach ($links as $link) {
            $system_link_names[] = $link->linkTitle;
        }

        return $system_link_names;
    }

    public function find_idx_page_type($idx_page)
    {
        //if it is a saved linke, return saved_link otherwise it is a system page
        $saved_links = $this->idx_api_get_savedlinks();
        foreach ($saved_links as $saved_link) {
            $id = $saved_link->id;
            if ($id === $idx_page) {
                return 'saved_link';
            }
        }
    }

    public function set_wrapper($idx_page, $wrapper_url)
    {
        //if none, quit process
        if ($idx_page === 'none') {
            return;
        } elseif ($idx_page === 'global') {
            //set Global Wrapper:
            $this->idx_api("dynamicwrapperurl", $this->idx_api_get_apiversion(), 'clients', array('body' => array('dynamicURL' => $wrapper_url)), 10, 'POST');
        } else {
            //find what IDX page type then set the page wrapper
            $page_type = $this->find_idx_page_type($idx_page);
            if ($page_type === 'saved_link') {
                $params = array(
                    'dynamicURL' => $wrapper_url,
                    'savedLinkID' => $idx_page,
                );
            } else {
                $params = array(
                    'dynamicURL' => $wrapper_url,
                    'pageID' => $idx_page,
                );
            }
            $this->idx_api("dynamicwrapperurl", $this->idx_api_get_apiversion(), 'clients', array('body' => $params), 10, 'POST');
        }
    }

    public function clear_wrapper_cache()
    {
        $idx_broker_key = $this->api_key;

        // access URL and request method

        $url = Initiate_Plugin::IDX_API_URL . '/clients/wrappercache';
        $method = 'DELETE';

        // headers (required and optional)
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'accesskey: ' . $idx_broker_key,
            'outputtype: json',
        );

        // set up cURL
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);

        // exec the cURL request and returned information. Store the returned HTTP code in $code for later reference
        $response = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($code == 204) {
            $response = true;
        } else {
            $response = false;
        }

        return $response;
    }

}
