<?php
/**
 * CRegularExpressionValidator class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CRegularExpressionValidator validates that the attribute value matches to the specified {@link pattern regular expression}.
 * You may invert the validation logic with help of the {@link not} property (available since 1.1.5).
 *
 * @author 
 * @version 
 * @package system.validators
 * @since 1.0
 */
class CRegularExpressionValidator extends CValidator
{
	/**
	 * @var string the regular expression to be matched with
	 */
	public $pattern;
	/**
	 * @var boolean whether the attribute value can be null or empty. Defaults to true,
	 * meaning that if the attribute is empty, it is considered valid.
	 */
	public $allowEmpty=true;
	/**
	 * @var boolean whether to invert the validation logic. Defaults to false. If set to true,
	 * the regular expression defined via {@link pattern} should NOT match the attribute value.
	 * @since 1.0
	 **/
 	public $not=false;

	/**
	 * Validates the attribute of the object.
	 * If there is any error, the error message is added to the object.
	 * @param CModel $object the object being validated
	 * @param string $attribute the attribute being validated
	 */
	protected function validateAttribute($object,$attribute)
	{
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		if($this->pattern===null)
			throw new CException(Mod::t('mod','The "pattern" property must be specified with a valid regular expression.'));
		if((!$this->not && !preg_match($this->pattern,$value)) || ($this->not && preg_match($this->pattern,$value)))
		{
			$message=$this->message!==null?$this->message:Mod::t('mod','{attribute} is invalid.');
			$this->addError($object,$attribute,$message);
		}
	}

	/**
	 * Returns the JavaScript needed for performing client-side validation.
	 * @param CModel $object the data object being validated
	 * @param string $attribute the name of the attribute to be validated.
	 * @return string the client-side validation script.
	 * @see CActiveForm::enableClientValidation
	 * @since 1.0
	 */
	public function clientValidateAttribute($object,$attribute)
	{
		if($this->pattern===null)
			throw new CException(Mod::t('mod','The "pattern" property must be specified with a valid regular expression.'));

		$message=$this->message!==null ? $this->message : Mod::t('mod','{attribute} is invalid.');
		$message=strtr($message, array(
			'{attribute}'=>$object->getAttributeLabel($attribute),
		));

		$pattern=$this->pattern;
		$pattern=preg_replace('/\\\\x\{?([0-9a-fA-F]+)\}?/', '\u$1', $pattern);
		$delim=substr($pattern, 0, 1);
		$endpos=strrpos($pattern, $delim, 1);
		$flag=substr($pattern, $endpos + 1);
		if ($delim!=='/')
			$pattern='/' . str_replace('/', '\\/', substr($pattern, 1, $endpos - 1)) . '/';
		else
			$pattern = substr($pattern, 0, $endpos + 1);
		if (!empty($flag))
			$pattern .= preg_replace('/[^igm]/', '', $flag);

		return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '').($this->not ? '' : '!')."value.match($pattern)) {
	messages.push(".CJSON::encode($message).");
}
";
	}
}