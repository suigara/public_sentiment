<?php
/**
 * CExceptionEvent class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CExceptionEvent represents the parameter for the {@link CApplication::onException onException} event.
 *
 * @author 
 * @version 
 * @package system.base
 * @since 1.0
 */
class CExceptionEvent extends CEvent
{
	/**
	 * @var CException the exception that this event is about.
	 */
	public $exception;

	/**
	 * Constructor.
	 * @param mixed $sender sender of the event
	 * @param CException $exception the exception
	 */
	public function __construct($sender,$exception)
	{
		$this->exception=$exception;
		parent::__construct($sender);
	}
}