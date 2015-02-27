<?php
require_once(dirname(__FILE__)."/../protocolbuf/message/pb_message.php");
class CounterRequest_RequestType extends PBEnum
{
  const requestTypeIncr  = 1;
  const requestTypeDecr  = 2;
  const requestTypeGet  = 3;
  const requestTypeExpire  = 4;
}
class CounterRequest extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBInt";
    $this->values["1"] = "";
    $this->fields["2"] = "PBString";
    $this->values["2"] = "";
    $this->fields["3"] = "CounterRequest_RequestType";
    $this->values["3"] = "";
    $this->fields["4"] = "PBString";
    $this->values["4"] = "";
    $this->fields["5"] = "PBInt";
    $this->values["5"] = "";
  }
  function bid()
  {
    return $this->_get_value("1");
  }
  function set_bid($value)
  {
    return $this->_set_value("1", $value);
  }
  function keyName()
  {
    return $this->_get_value("2");
  }
  function set_keyName($value)
  {
    return $this->_set_value("2", $value);
  }
  function requestType()
  {
    return $this->_get_value("3");
  }
  function set_requestType($value)
  {
    return $this->_set_value("3", $value);
  }
  function appName()
  {
    return $this->_get_value("4");
  }
  function set_appName($value)
  {
    return $this->_set_value("4", $value);
  }
  function expiredSeconds()
  {
    return $this->_get_value("5");
  }
  function set_expiredSeconds($value)
  {
    return $this->_set_value("5", $value);
  }
}
?>
