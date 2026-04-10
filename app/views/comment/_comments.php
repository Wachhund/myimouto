<div class="response-list">
  <?php foreach ($this->comments as $c) : ?>
    <?= $this->partial("comment/comment", ['comment' => $c]) ?>
  <?php endforeach ?>
</div>

<div style="clear: both;">
  <?php if ($this->hide) : ?>
    <?= $this->contentTag("h6", $this->linkToFunction($this->t('.reply'), "Comment.show_reply_form(" . $this->post_id . ");"), ['id' => 'respond-link-' . $this->post_id]) ?>
  <?php endif ?>
  
  <div id="reply-<?= $this->post_id ?>" style="<?= $this->hide ? "display: none;" : null ?>">
    <?= $this->formTag('comment#create', ['level' => 'member'], function () { ?>
      <?= $this->hiddenFieldTag("comment[post_id]", $this->post_id, ['id' => 'comment_post_id_' . $this->post_id]) ?>
      <?= $this->textArea("comment", "body", ['rows' => '7', 'id' => 'reply-text-' . $this->post_id, 'style' => 'width: 98%; margin-bottom: 2px;']) ?>
      <?= $this->submitTag($this->t('.post')) ?>
      <!-- <?= $this->submitTag($this->t('.bump')) ?> -->
    <?php }) ?>
<!--    <p style="margin-top: 1em; font-style: italic;">[spoiler]Hide spoiler text like this[/spoiler] (<?= $this->linkTo($this->t('.more'), 'help#comments') ?>)</p> -->
  </div>
</div>

<script type="text/javascript">
  <?= $this->avatar_init() ?> 
  InlineImage.init();
</script>

