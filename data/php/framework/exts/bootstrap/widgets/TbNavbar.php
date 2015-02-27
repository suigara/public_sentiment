<?php

Mod::import('bootstrap.helpers.TbHtml');
Mod::import('bootstrap.helpers.TbArray');
/**
 * Bootstrap navbar widget.
 * @see http://twitter.github.com/bootstrap/components.html#navbar
 */
class TbNavbar extends CWidget
{
    /**
     * @var string the navbar color.
     */
    public $color = TbHtml::NAVBAR_COLOR;
    /**
     * @var string the brand label text.
     */
    public $brandLabel;
    /**
     * @var mixed the brand url.
     */
    public $brandUrl;
    /**
     * @var array the HTML attributes for the brand link.
     */
    public $brandOptions = array();
    /**
     * @var string nanvbar display type.
     */
    public $position = TbHtml::NAVBAR_POSITION;
    /**
     * @var boolean whether to enable collapsing of the navbar on narrow screens.
     */
    public $collapse = false;
    /**
     * @var array additional HTML attributes for the collapse widget.
     */
    public $collapseOptions = array();
    /**
     * @var array list of navbar item.
     */
    public $items = array();
    /**
     * @var array the HTML attributes for the navbar.
     */
    public $htmlOptions = array();

    /**
     * Initializes the widget.
     */
    public function init()
    {
        if ($this->brandLabel !== false) {
            if (!isset($this->brandLabel)) {
                $this->brandLabel = CHtml::encode(Mod::app()->name);
            }

            if (!isset($this->brandUrl)) {
                $this->brandUrl = Mod::app()->homeUrl;
            }
        }
        if (isset($this->color)) {
            TbArray::defaultValue('color', $this->color, $this->htmlOptions);
        }
        if (isset($this->position) && $this->position !== TbHtml::NAVBAR_POSITION) {
            TbArray::defaultValue('position', $this->position, $this->htmlOptions);
        }
    }

    /**
     * Runs the widget.
     */
    public function run()
    {
        $brand = $this->brandLabel !== false
            ? TbHtml::navbarBrandLink($this->brandLabel, $this->brandUrl, $this->brandOptions)
            : '';
        ob_start();
        foreach ($this->items as $item) {
            if (is_string($item)) {
                echo $item;
            } else {
                $widgetClassName = TbArray::popValue('class', $item);
                if ($widgetClassName !== null) {
                    $this->controller->widget($widgetClassName, $item);
                }
            }
        }
        $items = ob_get_clean();
        ob_start();
        if ($this->collapse !== false) {
            TbHtml::addCssClass('collapse navbar-collapse', $this->collapseOptions);
            ob_start();
            /* @var TbCollapse $collapseWidget */
            $collapseWidget = $this->controller->widget(
                'bootstrap.widgets.TbCollapse',
                array(
                    'toggle' => false, // navbars are collapsed by default
                    'content' => $items,
                    'htmlOptions' => $this->collapseOptions,
                )
            );
            $collapseContent = ob_get_clean();
            $collapseLink =  TbHtml::navbarCollapseLink('#' . $collapseWidget->getId());

            echo TbHtml::tag('div',array('class'=>'navbar-header'),$collapseLink . $brand) . $collapseContent;

        } else {
            echo TbHtml::tag('div',array('class'=>'navbar-header'),$brand) . $items;
        }
        $containerContent = ob_get_clean();
        $containerOptions = TbArray::popValue('containerOptions', $this->htmlOptions, array());
        TbHtml::addCssClass( 'container', $containerOptions);

        $content = TbHtml::tag('div',$containerOptions,$containerContent);
        echo TbHtml::navbar($content, $this->htmlOptions);
    }
}