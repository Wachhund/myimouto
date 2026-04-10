<?php
!isset($this->include_tag_hover_highlight) && $this->include_tag_hover_highlight = false;
!isset($this->include_tag_reverse_aliases) && $this->include_tag_reverse_aliases = false;
?>
<div>
  <h5><?= $this->t('.title') ?></h5>
  <ul id="tag-sidebar">
    <?php !empty($this->tags['exclude']) && print $this->tag_links($this->tags['exclude'], ['prefix' => '-', 'with_hover_highlight' => 'true', 'with_hover_highlight' => $this->include_tag_hover_highlight]) ?>
    <?php !empty($this->tags['include']) && print $this->tag_links($this->tags['include'], ['with_aliases' => $this->include_tag_reverse_aliases, 'with_hover_highlight' => $this->include_tag_hover_highlight]) ?>
    <?php !empty($this->tags['related']) && print $this->tag_links(Tag::find_related($this->tags['related']), ['with_hover_highlight' => 'true', 'with_hover_highlight' => $this->include_tag_hover_highlight]) ?>
  </ul>
</div>
