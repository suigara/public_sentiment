<?php

Mod::import('zii.widgets.CListView');
Mod::import('bootstrap.helpers.TbHtml');

/**
 * Bootstrap Zii list view.
 */
class TbListView extends CListView
{
    /**
     * @var string the CSS class name for the pager container. Defaults to 'pagination'.
     */
    public $pagerCssClass = 'pagination';
    /**
     * @var array the configuration for the pager.
     * Defaults to <code>array('class'=>'ext.bootstrap.widgets.TbPager')</code>.
     */
    public $pager = array('class' => 'bootstrap.widgets.TbPager');
    /**
     * @var string the URL of the CSS file used by this detail view.
     * Defaults to false, meaning that no CSS will be included.
     */
    public $cssFile = false;
    /**
     * @var string the template to be used to control the layout of various sections in the view.
     */
    public $template = "{items}\n<div class=\"row-fluid\"><div class=\"col-md-6\">{pager}</div><div class=\"col-md-6\">{summary}</div></div>";

    /**
     * Renders the empty message when there is no data.
     */
    public function renderEmptyText()
    {
        $emptyText = $this->emptyText === null ? Mod::t('zii', 'No results found.') : $this->emptyText;
        echo TbHtml::tag('div', array('class' => 'empty', 'span' => 12), $emptyText);
    }
}
