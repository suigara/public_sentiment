<?php
/**
 * @author Nikolai Kordulla
 */
class PBSignedInt extends PBInt
{
	var $wired_type = PBMessage::WIRED_VARINT;

	/**
	 * Parses the message for this type
	 *
	 * @param array
	 */
	public function ParseFromArray()
	{
		parent::ParseFromArray();

        
		$saved = $this->value;
		$this->value = round($this->value / 2);
        if ($saved % 2 == 1)
		{
			$this->value = -($this->value);
		}
        // settype($this->value, 'int');
        $this->value = intval($this->value);
        //var_dump($this->value);
        
        /*$t = $this->value;
        //settype($t, 'int');
        //var_dump($t); echo "xxxxxx</br>";
        if($t > 0x7fffffff) {
            $t = -(0x100000000 - $t);
        } else if ($t == 0x80000000) {
            $t = 0;
        }

        $this->value = $t;
        */
	}

	/**
	 * Serializes type
	 */
	public function SerializeToString($rec=-1)
	{
		// now convert signed int to int
		$save = $this->value;
		if ($this->value < 0)
		{
			$this->value = abs($this->value)*2-1;
		}
		else 
		{
			$this->value = $this->value*2;
		}
		$string = parent::SerializeToString($rec);
		// restore value
		$this->value = $save;

		return $string;
	}
}
?>
