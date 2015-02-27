<?php
class CWaeOidb_proto extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBString";
    $this->values["1"] = "";
    $this->fields["2"] = "PBInt";
    $this->values["2"] = "";
    $this->fields["3"] = "PBString";
    $this->values["3"] = "";
    $this->fields["4"] = "PBInt";
    $this->values["4"] = "";
    $this->fields["5"] = "PBInt";
    $this->values["5"] = "";
    $this->fields["6"] = "PBString";
    $this->values["6"] = "";
    $this->fields["7"] = "PBInt";
    $this->values["7"] = "";
    $this->fields["8"] = "PBString";
    $this->values["8"] = "";
    $this->fields["9"] = "PBInt";
    $this->values["9"] = "";
    $this->fields["10"] = "PBString";
    $this->values["10"] = "";
    $this->fields["11"] = "PBInt";
    $this->values["11"] = "";
    $this->fields["12"] = "PBString";
    $this->values["12"] = "";
  }
  function result()
  {
    return $this->_get_value("1");
  }
  function set_result($value)
  {
    return $this->_set_value("1", $value);
  }
  function cmd()
  {
    return $this->_get_value("2");
  }
  function set_cmd($value)
  {
    return $this->_set_value("2", $value);
  }
  function uin()
  {
    return $this->_get_value("3");
  }
  function set_uin($value)
  {
    return $this->_set_value("3", $value);
  }
  function servicetype()
  {
    return $this->_get_value("4");
  }
  function set_servicetype($value)
  {
    return $this->_set_value("4", $value);
  }
  function skey_len()
  {
    return $this->_get_value("5");
  }
  function set_skey_len($value)
  {
    return $this->_set_value("5", $value);
  }
  function skey()
  {
    return $this->_get_value("6");
  }
  function set_skey($value)
  {
    return $this->_set_value("6", $value);
  }
  function waetype_len()
  {
    return $this->_get_value("7");
  }
  function set_waetype_len($value)
  {
    return $this->_set_value("7", $value);
  }
  function waetype()
  {
    return $this->_get_value("8");
  }
  function set_waetype($value)
  {
    return $this->_set_value("8", $value);
  }
  function waeappid_len()
  {
    return $this->_get_value("9");
  }
  function set_waeappid_len($value)
  {
    return $this->_set_value("9", $value);
  }
  function waeappid()
  {
    return $this->_get_value("10");
  }
  function set_waeappid($value)
  {
    return $this->_set_value("10", $value);
  }
  function data_len()
  {
    return $this->_get_value("11");
  }
  function set_data_len($value)
  {
    return $this->_set_value("11", $value);
  }
  function pdata()
  {
    return $this->_get_value("12");
  }
  function set_pdata($value)
  {
    return $this->_set_value("12", $value);
  }
}
?>