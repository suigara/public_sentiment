<?php

Mod::import('bootstrap.helpers.TbHtml');
Mod::import('bootstrap.helpers.TbArray');
Mod::import('zii.widgets.grid.CButtonColumn');

/**
 * Bootstrap button column widget.
 */
class TbButtonColumn extends CButtonColumn
{
    /**
     * @var string the view button icon (defaults to TbHtml::GLYPHICON_EYE_OPEN).
     */
    public $viewButtonIcon = TbHtml::GLYPHICON_EYE_OPEN;
    /**
     * @var string the update button icon (defaults to TbHtml::GLYPHICON_PENCIL).
     */
    public $updateButtonIcon = TbHtml::GLYPHICON_PENCIL;
    /**
     * @var string the delete button icon (defaults to TbHtml::GLYPHICON_TRASH).
     */
    public $deleteButtonIcon = TbHtml::GLYPHICON_TRASH;

    /**
     * Initializes the default buttons (view, update and delete).
     */
    protected function initDefaultButtons()
    {
        parent::initDefaultButtons();

        if ($this->viewButtonIcon !== false && !isset($this->buttons['view']['icon'])) {
            $this->buttons['view']['icon'] = $this->viewButtonIcon;
        }
        if ($this->updateButtonIcon !== false && !isset($this->buttons['update']['icon'])) {
            $this->buttons['update']['icon'] = $this->updateButtonIcon;
        }
        if ($this->deleteButtonIcon !== false && !isset($this->buttons['delete']['icon'])) {
            $this->buttons['delete']['icon'] = $this->deleteButtonIcon;
        }
    }

    /**
     * Renders a link button.
     * @param string $id the ID of the button
     * @param array $button the button configuration which may contain 'label', 'url', 'imageUrl' and 'options' elements.
     * @param integer $row the row number (zero-based)
     * @param mixed $data the data object associated with the row
     */
    protected function renderButton($id, $button, $row, $data)
    {

        if (isset($button['visible']) && !$this->evaluateExpression(
                $button['visible'],
                array('row' => $row, 'data' => $data)
            )
        ) {
            return;
        }

        $url = TbArrayTbArray::popValue('url', $button, '#');
        if (strcmp($url, '#') !== 0) {
            $url = $this->evaluateExpression($url, array('data' => $data, 'row' => $row));
        }

        $imageUrl = TbArrayTbArray::popValue('imageUrl', $button, false);
        $label = TbArrayTbArray::popValue('label', $button, $id);
        $options = TbArrayTbArray::popValue('options', $button, array());

        TbArrayTbArray::defaultValue('data-title', $label, $options);
        TbArrayTbArray::defaultValue('title', $label, $options);
        TbArrayTbArray::defaultValue('data-toggle', 'tooltip', $options);

        if ($icon = TbArrayTbArray::popValue('icon', $button, false)) {
            echo CHtml::link(TbHtml::icon($icon), $url, $options);
        } else {
            if ($imageUrl && is_string($imageUrl)) {
                echo CHtml::link(CHtml::image($imageUrl, $label), $url, $options);
            } else {
                echo CHtml::link($label, $url, $options);
            }
        }
    }
}
