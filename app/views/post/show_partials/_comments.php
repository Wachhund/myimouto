<div id="comments" style="margin-top: 1em; max-width: 800px; width: 100%;">
  <?= $this->partial("comment/comments", ['comments' => $this->post->comments, 'post_id' => $this->post->id, 'hide' => false]) ?>
</div>
