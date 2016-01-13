<?php
header('Content-Type: text/html; charset=utf-8');
if (!class_exists('WP_Http')) {
    include_once(ABSPATH . WPINC . '/class-http.php');
}

define('BLOGSOFT_BASE_URL', 'http://blogsoft.no/');
define('BLOGSOFT_OAUTH_ACCESS_URL', BLOGSOFT_BASE_URL . 'index.bd?fa=api2.token');
define('BLOGSOFT_OAUTH_AUTHORIZE_URL', BLOGSOFT_BASE_URL . 'index.bd?fa=account.app');
define('BLOGSOFT_API_CALL', BLOGSOFT_BASE_URL . 'index.bd?fa=api2.call');


class BlogsoftOAuth
{


    function BlogsoftOAuth()
    {
        $this->response_code = false;
        $this->error_message = false;

        $this->oauth_consumer_key = '';
        $this->oauth_consumer_secret = '';
    }

    function set_oauth_tokens($key, $secret)
    {
        $this->oauth_consumer_key = $key;
        $this->oauth_consumer_secret = $secret;
    }

    function get_response_code()
    {
        return $this->response_code;
    }

    function get_error_message()
    {
        return $this->error_message;
    }

    function encode($string)
    {
        return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($string)));
    }

    function params_to_query_string($params)
    {
        $query_string = array();
        foreach ($params as $key => $value) {
            $query_string[$key] = $key . '=' . $value;
        }

        ksort($query_string);

        return implode('&', $query_string);
    }

    function do_get_request($url)
    {

        $request = new WP_Http;
        $result = $request->request($url);

        $this->response_code = $result['response']['code'];
        if ($result['response']['code'] == '200') {
            return $result['body'];
        } else {
            return false;
        }
    }

    function do_request($url, $oauth_header, $body_params = '')
    {

        $request = new WP_Http;

        $params = array();
        if ($body_params) {
            foreach ($body_params as $key => $value) {
                $body_params[$key] = ($value);
            }

            $params['body'] = $body_params;
        }

        $params['method'] = 'POST';
        $params['headers'] = array('Authorization' => $oauth_header);
        $result = $request->request($url, $params);

        if (!is_wp_error($result)) {

            $this->response_code = $result['response']['code'];

            if ($result['response']['code'] == '200') {
                return $result['body'];
            } else {

                switch ($result['response']['code']) {
                    case 403:
                        $this->duplicate_tweet = true;
                        break;
                }
                $error_message_found = preg_match('#<error>(.*)</error>#i', $result['body'], $matches);
                if ($error_message_found) {
                    $this->error_message = $matches[1];
                }

            }

        }
        return false;
    }

    function parse_params($string_params)
    {
        $good_params = array();

        $params = explode('&', $string_params);
        foreach ($params as $param) {
            $keyvalue = explode('=', $param);
            $good_params[$keyvalue[0]] = $keyvalue[1];
        }

        return $good_params;
    }

    function do_oauth($url, $params, $token_secret = '')
    {

        $header = "OAuth ";
        $all_params = array();
        $other_params = array();
        foreach ($params as $key => $value) {
            if (strpos($key, 'oauth_') !== false) {
                $all_params[] = $key . '="' . $this->encode($value) . '"';
            } else {
                $other_params[$key] = $value;
            }
        }

        $header .= implode($all_params, ", ");

        return $this->do_request($url, $header, $other_params);
    }


    function get_authorize_url()
    {
        $params = array();
        $params['blog'] = get_bloginfo('name');
        $params['callback_uri'] = urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        return BLOGSOFT_OAUTH_AUTHORIZE_URL . '&' . $this->params_to_query_string($params);
    }


    function get_access_token($authorizeCode)
    {
        $params = array();
        $params['code'] = $authorizeCode;
        $params['grant_type'] = 'authorization_code';
        return $result = $this->do_oauth(BLOGSOFT_OAUTH_ACCESS_URL, $params);
    }

    function apiCall($params)
    {
        $result = $this->do_oauth(BLOGSOFT_API_CALL, $params);
        return json_decode($result, true);
    }
}
?>