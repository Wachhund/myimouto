<div style="margin-bottom: 1em;">
  <?= $this->formTag([], ['method' => 'get'], function(){ ?>
    <?= $this->textFieldTag("query", $this->h($this->params()->query)) ?>
    <?= $this->submitTag($this->t('.search')) ?>
    <?= $this->submitTag($this->t('tag_implication.index.search')) ?>
  <?php }) ?>
</div>

<div id="aliases">
  <?= $this->formTag(['action' => "update"], function() { ?>
    <table width="100%" class="highlightable">
      <thead>
        <tr>
          <th width="1%"></th>
          <th width="19%"><?= $this->t('.alias') ?></th>
          <th width="20%"><?= $this->t('.to') ?></th>
          <th width="60%"><?= $this->t('.reason') ?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <td colspan="4">
            <?php if (current_user()->is_mod_or_higher()) : ?>
              <?= $this->buttonToFunction($this->t('.pending'), "$$('.pending').each(function(x) {x.checked = true})") ?>
              <?= $this->submitTag($this->t('.approve')) ?>
            <?php endif ?>
            <?= $this->buttonToFunction($this->t('.delete'), "$('reason-box').show(); $('reason').focus()") ?>
            <?= $this->buttonToFunction($this->t('.add'), "$('add-box').show().scrollTo(); $('tag_alias_name').focus()") ?>

            <div id="reason-box" style="display: none; margin-top: 1em;">
              <strong><?= $this->t('.reason') ?></strong>
              <?= $this->textFieldTag("reason", "", ['size' => 40]) ?>
              <?= $this->submitTag($this->t('.delete')) ?>
            </div>
          </td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->aliases as $a) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?> <?= $a->is_pending ? 'pending-tag' : null ?>">
            <td><input type="checkbox" name="aliases[<?= $a->id ?>]" value="1" <?= $a->is_pending ? 'class="pending"' : null ?>></td>
            <td><?= $this->linkTo($this->h($a->name), ['controller' => "post", 'action' => "index", 'tags' => $a->name]) ?> (<?= ($tag = Tag::where(['name' => $a->name])->first()) ? $tag->post_count : 0 ?>)</td>
            <td><?= $this->linkTo($this->h($a->alias_name()), ['controller' => "post", 'action' => "index", 'tags' => $a->alias_name()]) ?> (<?= ($tag = Tag::find($a->alias_id)) ? $tag->post_count : 0 ?>)</td>
            <td><?= $this->h($a->reason) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php }) ?>
</div>

<div id="add-box" style="display: none;">
  <?= $this->formTag(['action' => "create"], function() { ?>
    <h4><?= $this->t('.add_new.title') ?></h4>
    <p><?= $this->t('.add_new.info') ?></p>

    <?php if (!current_user()->is_anonymous()) : ?>
      <?= $this->hiddenFieldTag("tag_alias[creator_id]", current_user()->id) ?>
    <?php endif ?>

    <table>
      <tr>
        <th><label for="tag_alias_name"><?= $this->t('.add_new.name') ?></label></th>
        <td><?= $this->textField('tag_alias', 'name', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="tag_alias_alias"><?= $this->t('.add_new.alias_to') ?></label></th>
        <td><?= $this->textField('tag_alias', 'alias', ['size' => 40]) ?></td>
      </tr>
      <tr>
        <th><label for="tag_alias_reason"><?= $this->t('.reason') ?></label></th>
        <td><?= $this->textArea('tag_alias', 'reason', ['size' => "40x2"]) ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('.add_new.submit')) ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>

<div id="paginator">
  <?= $this->willPaginate($this->aliases) ?>
</div>

<?= $this->partial("/tag/footer") ?>
