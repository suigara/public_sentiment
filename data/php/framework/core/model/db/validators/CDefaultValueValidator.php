<?php
/**
 * CDefaultValueValidator class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CDefaultValueValidator sets the attributes with the specified value.
 * It does not do validation. Its existence is mainly to allow
 * specifying attribute default values in a dynamic way.
 *
 * @author 
 * @version 
 * @package system.validators
 */
class CDefaultValueValidator extends CValidator
{
	/**
	 * @var mixed the default value to be set to the specified attributes.
	 */
	public $value;
	/**
	 * @var boolean whether to set the default value only when the attribute value is null or empty string.
	 * Defaults to true. If false, the attribute will always be assigned with the default value,
	 * even if it is already explicitly assigned a value.
	 */
	public $setOnEmpty=true;

	/**
	 * Validates the attribute of the object.
	 * @param CModel $object the object being validated
	 * @param string $attribute the attribute being validated
	 */
	protected function validateAttribute($object,$attribute)
	{
		if(!$this->setOnEmpty)
			$object->$attribute=$this->value;
		else
		{
			$value=$object->$attribute;
			if($value===null || $value==='')
				$object->$attribute=$this->value;
		}
	}
}

