<?php

/**
 * Curl wrapper for Mod
 * v - 1.2
 * @author hackerone
 */
class CUrl extends CApplicationComponent {

	// use proxy , defualt false
	public $useProxy ;

	private $_ch;
    // config from config.php
    public $options;
    // default config
    private $_config = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:5.0) Gecko/20110619 Firefox/5.0'
    );

	
    public function resCode()
    {
	   return curl_getinfo($this->_ch,CURLINFO_HTTP_CODE);
    }

    public function _exec($url) {

        $this->setOption(CURLOPT_URL, $this->__proxy($url));
        $c = curl_exec($this->_ch);
        if (!curl_errno($this->_ch))
            return $c;
        else
            throw new CException(curl_error($this->_ch));
    }

    public function get($url, $params = array()) {
        $this->setOption(CURLOPT_HTTPGET, true);
        return $this->_exec($this->buildUrl($url, $params));
    }

    public function post($url, $data = array()) {
        $this->setOption(CURLOPT_POST, true);
        if(is_array($data) || is_object($data))
        {       
            $data = http_build_query($data);  
        }       
        $this->setOption(CURLOPT_POSTFIELDS, $data); 
        return $this->_exec($url);
    }

    public function put($url, $data, $params = array()) {

        // write to memory/temp
        $f = fopen('php://temp', 'rw+');
        fwrite($f, $data);
        rewind($f);

        $this->setOption(CURLOPT_PUT, true);
        $this->setOption(CURLOPT_INFILE, $f);
        $this->setOption(CURLOPT_INFILESIZE, strlen($data));

        return $this->_exec($this->buildUrl($url, $params));
    }

    public function buildUrl($url, $data = array()) {
        $parsed = parse_url($url);
        isset($parsed['query']) ? parse_str($parsed['query'], $parsed['query']) : $parsed['query'] = array();
        $params = isset($parsed['query']) ? array_merge($parsed['query'], $data) : $data;
        $parsed['query'] = ($params) ? '?' . http_build_query($params) : '';
        if (!isset($parsed['path']))
            $parsed['path'] = '/';
        
        $port = '';
        if(isset($parsed['port'])){
            $port = ':' . $parsed['port'];
        }
        
        return $parsed['scheme'] . '://' . $parsed['host'] .$port. $parsed['path'] . $parsed['query'];
    }

    public function setOptions($options = array()) {
        curl_setopt_array($this->_ch, $options);
        return $this;
    }

    public function setOption($option, $value) {
        curl_setopt($this->_ch, $option, $value);
        return $this;
    }

    // initialize curl
    public function init() {
		parent::init();
		if(!isset($this->useProxy)){
			$this->useProxy = Mod::app()->params['useProxy'];
			if(!isset($this->useProxy)) $this->useProxy = false;
		}
        try {
            $this->_ch = curl_init();
            $options = is_array($this->options) ? ($this->options + $this->_config) : $this->_config;
            $this->setOptions($options);

            // close curl on exit
            Mod::app()->onEndRequest = array($this, 'close');
        } catch (Exception $e) {
            throw new CException('Curl not installed');
        }
    }
    
    public function close()
    {
    	curl_close($this->_ch);
    }
    
    public function getHttpCode()
    {
        return curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
    }
    
    public function getHttpInfo()
    {
        return curl_getinfo($this->_ch);
    }
	
    private function __proxy($url)
    {
	if($this->useProxy === true)
		return 'http://mp.seals.webdev.com/proxy/proxyServe?url='.urlencode($url); 
	return $url;
    }

    
}
