<?php
/**
 * BbCollapse class file.
 * based on Mod-strap TbCollapse
 * @see https://raw.github.com/Crisu83/Modstrap/master/widgets/TbCollapse.php
 */

Mod::import('bootstrap.behaviors.TbWidget');
Mod::import('bootstrap.helpers.TbHtml');
Mod::import('bootstrap.helpers.TbArray');

/**
 * Bootstrap collapse widget.
 * @see http://getbootstrap.com/javascript/#collapse
 */
class TbCollapse extends CWidget
{
    /**
     * @var string the HTML tag for the container.
     */
    public $tagName = 'div';
    /**
     * @var string the content text.
     */
    public $content;
    /**
     * @var string the path to a partial view.
     */
    public $view;
    /**
     * @var string the CSS selector for the parent element.
     */
    public $parent;
    /**
     * @var boolean whether to be collapsed on invocation.
     */
    public $toggle;
    /**
     * @var string[] $events the JavaScript event configuration (name=>handler).
     */
    public $events = array();
    /**
     * @var array the HTML attributes for the container.
     */
    public $htmlOptions = array();
    /**
     * @var array additional data to be passed to the view.
     */
    public $viewData = array();

    /**
     * Initializes the widget.
     */
    public function init()
    {
        Mod::app()->clientScript->registerCoreScript('jquery');
        $this->attachBehavior('TbWidget', new TbWidget);
        $this->copyId();
        TbHtml::addCssClass('collapse', $this->htmlOptions);
        if (isset($this->parent)) {
            TbArray::defaultValue('data-parent', $this->parent, $this->htmlOptions);
        }
        if (isset($this->toggle) && $this->toggle) {
            TbHtml::addCssClass('in', $this->htmlOptions);
        }
        if (isset($this->view)) {
            $controller = $this->getController();
            if (isset($controller) && $controller->getViewFile($this->view) !== false) {
                $this->content = $this->controller->renderPartial($this->view, $this->viewData, true);
            }
        }
        echo TbHtml::openTag($this->tagName, $this->htmlOptions);
        echo $this->content;
    }

    /**
     * Runs the widget.
     */
    public function run()
    {
        echo TbHtml::closeTag($this->tagName);
        $selector = '#' . $this->htmlOptions['id'];
        $this->registerEvents($selector, $this->events);
    }
}