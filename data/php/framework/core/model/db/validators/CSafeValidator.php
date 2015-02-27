<?php
/**
 * CSafeValidator class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CSafeValidator marks the associated attributes to be safe for massive assignments.
 *
 * @author 
 * @version 
 * @package system.validators
 * @since 1.0
 */
class CSafeValidator extends CValidator
{
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

