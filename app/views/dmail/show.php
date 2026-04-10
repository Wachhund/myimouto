<div id="mail-show" class="mail">
  <div id="previous-messages" style="display: none;">
  </div>

  <?= $this->partial("message", ['message' => $this->dmail]) ?>

  <div style="width: 50em; display: none;" id="response">
    <?= $this->partial("compose", ['from_id' => current_user()->id]) ?>
  </div>

  <?php $this->contentFor('footer', function () { ?>
    <li><?= $this->linkToFunction("Show conversation", "Dmail.expand(" . ($this->dmail->parent_id ?: $this->dmail->id) . ", " . $this->dmail->id . ")") ?></li>
    <?php if ($this->dmail->to_id == $this->current_user->id) : ?>
      <li><?= $this->linkToFunction("Respond", "Dmail.respond('" . $this->dmail->from->name . "')") ?></li>
    <?php endif ?>
  <?php }) ?>

  <?= $this->partial("footer") ?>
</div>
