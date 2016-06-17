<?php

require_once 'Net/LDAP2.php';        

class MyTestLdap {

    protected $_entries = array();

    public function __construct($ldif_files) {
        //error_log("constructing MyTestLdap from ldif_files");
        foreach ($ldif_files as $file) {
            $entry = MyTestLdap::read_one_ldif($file);
            $this->add_entry($entry);
        }
    }

    public function search($base, $filter, $attributes, $sizelimit = 0, $timelimit = 0) {
        return $this->_search($base, $filter, $attributes, $sizelimit);
    }
    
        
    public function close() {
        return false;
    }

    protected function add_entry($entry) {
        // allow searching on (entryDN=...)
        $entry->add(array('entryDN' => $entry->dn()));
            
        $branch = preg_replace("/^.*?,/", '', $entry->dn());
        if (!isset($this->_entries[$branch])) {
            $this->_entries[$branch] = array();
        }
        $this->_entries[$branch][] = $entry;
    }
    

    private function _search($base, $filter, $attributes, $sizelimit) {
        //error_log("looking for $base $filter");
        $filter_ = Net_LDAP2_Filter::parse($filter);
        if ($filter_ instanceof PEAR_Error) {
            error_log("filter $filter invalid (?)");
            error_log($filter_->message);
        }
        $r = array("count" => 0);
        foreach ($this->_entries as $branch => $entries) {
            if ($base && !preg_match("/$base\$/", $branch)) continue;
            foreach ($entries as $entry) {
                if ($filter_->matches($entry)) {
                    $r[] = MyTestLdap::get_one_entry($entry, $attributes);
                    $r["count"] = $r["count"] + 1;
                    if ($r["count"] == $sizelimit) return $r;
                }
            }
        }
        return $r;
    }

    public static function read_one_ldif($file) {
        // open some LDIF file for reading
        $ldif = new Net_LDAP2_LDIF($file, 'r', array('onerror' => 'die'));
        $entry = $ldif->read_entry();
        $ldif->done();
        return $entry;
    }
    
    public static function get_one_entry($entry, $attributes) {
        $e = array();
        foreach ($attributes as $attr) {
            $vals = $entry->getValue($attr, 'all');
            if (count($vals)) {
                $vals["count"] = count($vals); // compat with ldap_get_entries
                $e[strtolower($attr)] = $vals;
            }
        }
        return $e;
    }
    
}