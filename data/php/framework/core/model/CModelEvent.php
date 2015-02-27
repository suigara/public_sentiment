<?php
/**
 * CModelEvent class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */


/**
 * CModelEvent class.
 *
 * CModelEvent represents the event parameters needed by events raised by a model.
 *
 * @author 
 * @version 
 * @package system.base
 * @since 1.0
 */
class CModelEvent extends CEvent
{
	/**
	 * @var boolean whether the model is in valid status and should continue its normal method execution cycles. Defaults to true.
	 * For example, when this event is raised in a {@link CFormModel} object that is executing {@link CModel::beforeValidate},
	 * if this property is set false by the event handler, the {@link CModel::validate} method will quit after handling this event.
	 * If true, the normal execution cycles will continue, including performing the real validations and calling
	 * {@link CModel::afterValidate}.
	 */
	public $isValid=true;
	/**
	 * @var CDbCrireria the query criteria that is passed as a parameter to a find method of {@link CActiveRecord}.
	 * Note that this property is only used by {@link CActiveRecord::onBeforeFind} event.
	 * This property could be null.
	 * @since 1.0
	 */
	public $criteria;
}
