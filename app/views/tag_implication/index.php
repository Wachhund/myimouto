<div style="margin-bottom: 1em;">
  <?= $this->formTag(['action' => "index"], ['method' => 'get'], function(){ ?>
    <?= $this->textFieldTag("query", $this->h($this->params()->query)) ?>
    <?= $this->submitTag($this->t('.search')) ?>
    <?= $this->submitTag($this->t('tag_alias.index.search')) ?>
  <?php }) ?>
</div>

<?= $this->formTag(['action' => "update"], function() { ?>
  <table class="highlightable" width="100%">
    <thead>
      <tr>
        <th width="1%"></th>
        <th width="19%"><?= $this->t('.predicate') ?></th>
        <th width="20%"><?= $this->t('.consequent') ?></th>
        <th width="60%"><?= $this->t('.reason') ?></th>
      </tr>
    </thead>
    <tfoot>
      <tr>
        <td colspan="4">
          <?php if (current_user()->is_mod_or_higher()) : ?>
            <?= $this->buttonToFunction($this->t('.select_pending'), "$$('.pending').each(function(x) {x.checked = true})") ?>
            <?= $this->submitTag($this->t('.approve')) ?>
          <?php endif ?>
          <?= $this->buttonToFunction($this->t('.delete'), "$('reason-box').show(); $('reason').focus()") ?>
          <?= $this->buttonToFunction($this->t('.add'), "$('add-box').show().scrollTo(); $('tag_implication_predicate').focus()") ?>

          <div id="reason-box" style="display: none; margin-top: 1em;">
            <strong><?= $this->t('.reason') ?></strong>
            <?= $this->textFieldTag("reason", "", ['size' => 40]) ?>
            <?= $this->submitTag($this->t('.delete')) ?>
          </div>
        </td>
      </tr>
    </tfoot>
    <tbody>
      <?php foreach ($this->implications as $i) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?> <?= $i->is_pending ? 'pending-tag' : null ?>">
          <td><input type="checkbox" value="1" name="implications[<?= $i->id ?>]" <?= $i->is_pending ? 'class="pending"' : null ?>></td>
          <td><?= $this->linkTo($this->h($i->predicate->name), ['controller' => "post", 'action' => "index", 'tags' => $i->predicate->name]) ?> (<?= $i->predicate->post_count ?>)</td>
          <td><?= $this->linkTo($this->h($i->consequent->name), ['controller' => "post", 'action' => "index", 'tags' => $i->consequent->name]) ?> (<?= $i->consequent->post_count ?>)</td>
          <td><?= $this->h($i->reason) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php }) ?>

<div id="add-box" style="display: none;">
  <?= $this->formTag(['action' => "create"], function() { ?>
    <h4><?= $this->t('.add_new.title') ?></h4>
    <p><?= $this->t('.add_new.info_moderation') ?></p>
    <p><?= $this->t('.add_new.info') ?></p>
    <?php if (!current_user()->is_anonymous()) : ?>
      <?= $this->hiddenFieldTag("tag_implication[creator_id]", current_user()->id) ?>
    <?php endif ?>

    <table>
      <tr>
        <th><label for="tag_implication_predicate"><?= $this->t('.predicate') ?></label></th>
        <td><?= $this->textField('tag_implication', 'predicate', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="tag_implication_consequent"><?= $this->t('.consequent') ?></label></th>
        <td><?= $this->textField('tag_implication', 'consequent', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="tag_implication_reason"><?= $this->t('.reason') ?></label></th>
        <td><?= $this->textArea('tag_implication', 'reason', ['size' => "40x2"]) ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('.add_new.submit')) ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>

<div id="paginator">
  <?= $this->willPaginate($this->implications) ?>
</div>

<?= $this->partial("tag/footer") ?>
