<?php
/**
 * ModBase is a helper class serving common framework functionalities.
 * @author andehuang
 */

define(MOD_BASE, true);
require(dirname(__FILE__).'/Mod.php');

class ModBase extends Mod
{
	/**
	 * @var CModule 组件池对象
	 */
	public static $module;
	
	/**
	 * @var array 定义每个组件需要使用的类，在创建组件的时候，一次性将这个组件需要使用到得所有类加载进来
	 * 当只使用Mod框架的组件池的时候，使用这种方式来避免使用autoload机制，从而简化类的加载关系
	 */
	public static $componentDependencies = array(
		
	);
	
	/**
	 * 初始化组件池对象
	 */
	public static function init()
	{
		if(!self::$module)
		{
			$initRequiredClass = array('CComponent', 'CBehavior', 'CApplicationComponent', 'CModule');
			foreach($initRequiredClass as $className)
				self::import($className);
			self::$module = new CModule;
		}
	}
	
	/**
	 * 加载类，如果是组件类则还需要加载该组件需要的所有类
	 * @param string $className
	 */
	public static function import($className)
	{
		if(class_exists($className, false))
		{
			if(!isset(self::$coreClasses[$className]))
				throw new Exception("class:$className no defined in Mod::\$coreClasses");
			require(MOD_PATH.self::$coreClasses[$className]);
			//if 
			if(isset(self::$componentDependencies[]))
			{
				
			}
		}
	}
	
	/**
	 * 添加组件配置，可以多次调用本方法来批量添加组件，如果2个组件的id一样，那么后添加的组件将覆盖原先的
	 * 该方法是CModule的setComponents方法的一个简单封装
	 * @param array $components 组件配置数组，每个组件的配置都是一个数组，组件的id必须唯一并且命名有意义
	 * @param boolean $merge 是否与原先的组件配置进行合并，这个参数的好处是，后添加的组件只需填写变化的配置项
	 */
	public static function setComponents($components,$merge=true)
	{
		self::$module->setComponents($components,$merge);
	}
}

/**
 * 通过本函数可以更加方便的获取组件
 * @return CModule 组件池
 */
function Mod()
{
	return Mod::$module;
}

Mod::init();