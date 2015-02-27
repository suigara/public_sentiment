<?php
require_once(dirname(__FILE__)."/../protocolbuf/message/pb_message.php");
class CounterRes extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBString";
    $this->values["1"] = "";
    $this->fields["2"] = "PBString";
    $this->values["2"] = "";
  }
  function rescode()
  {
    return $this->_get_value("1");
  }
  function set_rescode($value)
  {
    return $this->_set_value("1", $value);
  }
  function keyVaule()
  {
    return $this->_get_value("2");
  }
  function set_keyVaule($value)
  {
    return $this->_set_value("2", $value);
  }
}
?>
