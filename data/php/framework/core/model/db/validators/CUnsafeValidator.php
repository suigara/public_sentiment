<?php
/**
 * CUnsafeValidator class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CUnsafeValidator marks the associated attributes to be unsafe so that they cannot be massively assigned.
 *
 * @author 
 * @version 
 * @package system.validators
 * @since 1.0
 */
class CUnsafeValidator extends CValidator
{
	/**
	 * @var boolean whether attributes listed with this validator should be considered safe for massive assignment.
	 * Defaults to false.
	 * @since 1.0
	 */
	public $safe=false;
	/**
	 * Validates the attribute of the object.
	 * If there is any error, the error message is added to the object.
	 * @param CModel $object the object being validated
	 * @param string $attribute the attribute being validated
	 */
	protected function validateAttribute($object,$attribute)
	{
	}
}

