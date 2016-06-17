<?php

class MyLdap {

    protected $_ds = NULL;

    public static function connect($config) {
        if (isset($config['test_ldif_files'])) {
            require_once 'test/MyTestLdap.inc.php';
            return new MyTestLdap($config['test_ldif_files']);
        } else {
            return new MyLdap($config['HOST'], $config['BIND_DN'], $config['BIND_PASSWORD']);
        }
    }
    
    public function __construct($host, $dn, $password) {
        $this->_ds = ldap_connect($host);
        if (!$this->_ds) {
            exit("error: connection to $host failed");
        }

        if (!ldap_bind($this->_ds, $dn, $password)) {
            exit("error: failed to bind using $dn");
        }
    }

    public function search($base, $filter, $attributes, $sizelimit = 0, $timelimit = 0) {
        $search_result = @ldap_search($this->_ds, $base, $filter, $attributes, 0, $sizelimit, $timelimit);
        if (!$search_result) return NULL;

        return ldap_get_entries($this->_ds, $search_result);
    }
        
    public function close() {
        if ($this->_ds) {
            ldap_close($this->_ds);
            $this->_ds = NULL;
            return true;
        } else {
            return false;
        }
    }
}
