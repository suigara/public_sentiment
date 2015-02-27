<?php

class TbPortlet extends CWidget
{
	
	/**
	 * @var string the tag name for the portlet container tag. Defaults to 'div'.
	 */
	public $tagName='div';
	/**
	 * @var array the HTML attributes for the portlet container tag.
	 */
	public $htmlOptions=array('class'=>'panel');
	/**
	 * @var string the title of the portlet. Defaults to null.
	 * When this is not set, Decoration will not be displayed.
	 * Note that the title will not be HTML-encoded when rendering.
	 */
	public $title;
	/**
	 * @var string the CSS class for the decoration container tag. Defaults to 'panel-heading'.
	 */
	public $headerCssClass='panel-heading';
	/**
	 * @var string the CSS class for the portlet title tag. Defaults to 'panel-title'.
	 */
	public $titleCssClass='panel-title';
	/**
	 * @var string the CSS class for the content container tag. Defaults to 'panel-content'.
	 */
	public $contentCssClass='panel-body';
	/**
	 * @var boolean whether to hide the portlet when the body content is empty. Defaults to true.
	 * @since 1.1.4
	 */
	public $hideOnEmpty=true;
	
	/**
     * @var string the type of the panel
     */
	public $type ;
	
	/**
     * @var boolean whether to enable collapsing of the panel header
     */
	public $collapse = false;
	 /**
     * @var array additional HTML attributes for the collapse widget.
     */
	public $collapseOptions = array();
	
	private $_openTag;

	/**
	 * Initializes the widget.
	 * This renders the open tags needed by the portlet.
	 * It also renders the decoration, if any.
	 */
	public function init()
	{
		ob_start();
		ob_implicit_flush(false);
		
		if(empty($this->type)){
			$this->htmlOptions['class'].=" panel-default";
		}else{
			$this->htmlOptions['class'].=" panel-".$this->type;
		}		
		$this->htmlOptions['id']=$this->getId();
		echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
		$this->_openTag=ob_get_contents();
		ob_clean();
	}

	/**
	 * Renders the content of the portlet.
	 */
	public function run()
	{
		$this->renderContent();
		$content=ob_get_clean();
		if($this->hideOnEmpty && trim($content)==='')
			return;
		$title = $this->title;
		if( !empty($title) && $this->collapse !== false )
		{
			ob_start();
			TbHtml::addCssClass(" collapse panel-collapse ", $this->collapseOptions);
            $collapseWidget = $this->controller->widget(
                'bootstrap.widgets.TbCollapse',
                array(
                    'toggle' => true, 
                    'content' => $content,
                    'htmlOptions' => $this->collapseOptions,
                )
            );
            $content = ob_get_clean();
            $title =  TbHtml::collapseLink(
					$this->title, 
					'#' . $collapseWidget->getId(), 
					array(
						"data-parent"=>"#".$this->getId(),
						"class"=>"accordion-toggle"
				    )
			);			
		}else{
			$content = "<div class=\"{$this->contentCssClass}\">\n".$content."</div>\n";
		}
		echo $this->_openTag;
		if(!empty($title))
		{
			echo "<div class=\"{$this->headerCssClass}\">\n";
			echo "<h4 class=\"{$this->titleCssClass}\">{$title}</h4>\n";
			echo "</div>\n";
		}
		echo $content;
		echo CHtml::closeTag($this->tagName);
	}

	
	/**
	 * Renders the content of the portlet.
	 * Child classes should override this method to render the actual content.
	 */
	protected function renderContent()
	{
	}
	
	

}