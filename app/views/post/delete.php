<h4><?= $this->t('.title') ?></h4>

<?php if (CONFIG()->can_see_post(current_user(), $this->post)) : ?>
  <?= $this->imageTag($this->post->preview_url()) ?>
<?php endif ?>

<?= $this->formTag(['action' => 'destroy'], function () { ?>
  <?= $this->hiddenFieldTag("id", $this->params()->id) ?>
  <label><?= $this->t('.reason') ?></label> <?= $this->textFieldTag("reason", CONFIG()->default_post_delete_reason) ?>
  <?php if ($this->post->is_deleted()) : ?>
  <?= $this->hiddenFieldTag("destroy", "1") ?>
  <?php endif ?>
  <?= $this->submitTag($this->post->is_deleted() ? $this->t('.permanent') : $this->t('.delete')) ?> <?= $this->submitTag($this->t('buttons.cancel')) ?>
<?php }) ?>

<div class="deleting-post">
<?php if (!$this->post->is_deleted()) : ?>
    <br>
    <p>
    <?php if ($this->post_parent) : ?>
      <?= $this->t('.parent_info') ?><p>
    <?php if (CONFIG()->can_see_post(current_user(), $this->post_parent)) : ?>
      <ul id="post-list-posts"> <?= $this->print_preview($this->post_parent, ['hide_directlink' => 'true']) ?> </ul>
    <?php else: ?>
      <?= $this->t('.noaccess_info') ?>
    <?php endif ?>

    <?php else: ?>
      <?= $this->t('.noparent_info') ?><p>
    <?php endif ?>
<?php else: ?>
  <?= $this->t('.permanent_info') ?>
<?php endif ?>
</div>

<?= $this->partial("footer") ?>

<script type="text/javascript">$("reason").focus();</script>
