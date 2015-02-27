<?php
require_once(Mod::getPathOfAlias('system.exts.smarty').DIRECTORY_SEPARATOR.'Smarty.class.php');  
    define('SMARTY_VIEW_DIR', Mod::getPathOfAlias('application.views'));  
     
    class CSmarty extends Smarty {  
        const DIR_SEP = DIRECTORY_SEPARATOR;  
        function __construct() {  
           parent::__construct();  
             
            $this->template_dir = SMARTY_VIEW_DIR; 
            $this->compile_dir = SMARTY_VIEW_DIR.self::DIR_SEP.'template_c';  
            $this->caching = false;  
            $this->cache_dir = SMARTY_VIEW_DIR.self::DIR_SEP.'cache';  
            $this->left_delimiter  =  '{';  
            $this->right_delimiter =  '}';  
            $this->cache_lifetime = 3600; 
        }  
        function init() {}  
    }
?>