<?php
/**
 * This file contains CTypedMap class.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CTypedMap represents a map whose items are of the certain type.
 *
 * CTypedMap extends {@link CMap} by making sure that the elements to be
 * added to the list is of certain class type.
 *
 * @author 
 * @version 
 * @package system.collections
 * @since 1.0
 */
class CTypedMap extends CMap
{
	private $_type;

	/**
	 * Constructor.
	 * @param string $type class type
	 */
	public function __construct($type)
	{
		$this->_type=$type;
	}

	/**
	 * Adds an item into the map.
	 * This method overrides the parent implementation by
	 * checking the item to be inserted is of certain type.
	 * @param integer $index the specified position.
	 * @param mixed $item new item
	 * @throws CException If the index specified exceeds the bound,
	 * the map is read-only or the element is not of the expected type.
	 */
	public function add($index,$item)
	{
		if($item instanceof $this->_type)
			parent::add($index,$item);
		else
			throw new CException(Mod::t('mod','CTypedMap<{type}> can only hold objects of {type} class.',
				array('{type}'=>$this->_type)));
	}
}
