<?php
class ip_req_c extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBInt";
    $this->values["1"] = "";
    $this->fields["2"] = "PBString";
    $this->values["2"] = "";
  }
  function msg_seq()
  {
    return $this->_get_value("1");
  }
  function set_msg_seq($value)
  {
    return $this->_set_value("1", $value);
  }
  function ip()
  {
    return $this->_get_value("2");
  }
  function set_ip($value)
  {
    return $this->_set_value("2", $value);
  }
}
class ip_rsp_c extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBInt";
    $this->values["1"] = "";
    $this->fields["2"] = "PBString";
    $this->values["2"] = "";
    $this->fields["3"] = "PBInt";
    $this->values["3"] = "";
    $this->fields["4"] = "PBString";
    $this->values["4"] = "";
  }
  function ret()
  {
    return $this->_get_value("1");
  }
  function set_ret($value)
  {
    return $this->_set_value("1", $value);
  }
  function desc()
  {
    return $this->_get_value("2");
  }
  function set_desc($value)
  {
    return $this->_set_value("2", $value);
  }
  function pid()
  {
    return $this->_get_value("3");
  }
  function set_pid($value)
  {
    return $this->_set_value("3", $value);
  }
  function province()
  {
    return $this->_get_value("4");
  }
  function set_province($value)
  {
    return $this->_set_value("4", $value);
  }
}
class ip_proto_c_op extends PBEnum
{
  const IP_REQ  = 0;
  const IP_RSP  = 1;
}
class ip_proto_c extends PBMessage
{
  var $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
  public function __construct($reader=null)
  {
    parent::__construct($reader);
    $this->fields["1"] = "PBInt";
    $this->values["1"] = "";
    $this->fields["2"] = "PBInt";
    $this->values["2"] = "";
    $this->fields["3"] = "ip_req_c";
    $this->values["3"] = "";
    $this->fields["4"] = "ip_rsp_c";
    $this->values["4"] = "";
  }
  function req_op()
  {
    return $this->_get_value("1");
  }
  function set_req_op($value)
  {
    return $this->_set_value("1", $value);
  }
  function sid()
  {
    return $this->_get_value("2");
  }
  function set_sid($value)
  {
    return $this->_set_value("2", $value);
  }
  function ip_req()
  {
    return $this->_get_value("3");
  }
  function set_ip_req($value)
  {
    return $this->_set_value("3", $value);
  }
  function ip_rsp()
  {
    return $this->_get_value("4");
  }
  function set_ip_rsp($value)
  {
    return $this->_set_value("4", $value);
  }
}
?>