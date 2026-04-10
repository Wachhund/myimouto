<?php
$fields = '';
$thumbnails = '';
foreach ($this->posts as $i => $p) {
    $fields .= $this->hiddenFieldTag("posts[{$p->id}]", str_pad((string) $i, 5, '0', STR_PAD_LEFT));
    $thumbnails .= $this->print_preview($p, ['onclick' => "return removePost({$p->id})"]);
}
?>
<div style="margin-bottom: 2em;">
  <?= $this->checkBoxTag("delete-mode") ?>
  <?= $this->contentTag('label', "Remove posts", ['onclick' => "Element.toggle('delete-mode-help')", 'for' => "delete-mode"]) ?>
  <?= $this->contentTag('p', $this->contentTag('em', "When delete mode is enabled, clicking on a thumbnail will remove that post from the import."), ['style' => "display: none;", 'id' => "delete-mode-help"]) ?>
</div>
<?= $fields ?>
<?= $thumbnails ?>
