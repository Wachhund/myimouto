<?= $this->formTag(['action' => "update"], function () { ?>
  <table class="form">
    <tr>
      <th width="15%"><label for="tag_name"><?= $this->t('tag_name') ?></label></th>
      <td width="85%"><input class="ac-tag-name ui-autocomplete-input" id="tag_name" name="tag[name]" size="30" type="text" value="<?= $this->tag->name ?>" autocomplete="off" role="textbox" aria-autocomplete="list" aria-haspopup="true"/></td>
    </tr>
    <tr>
      <th><label for="tag_type"><?= $this->t('tag_type') ?></label></th>
      <td><?= $this->select("tag", "tag_type", array_unique(CONFIG()->tag_types)) ?></td>
    </tr>
    <tr>
      <th><label for="tag_is_ambiguous"><?= $this->t('tag_amb') ?></label></th>
      <td><?= $this->checkBox("tag", "is_ambiguous") ?></td>
    </tr>
    <tr>
      <td colspan="2"><?= $this->submitTag($this->t('tag_save')) ?> <?= $this->buttonToFunction($this->t('tag_cancel'), "history.back()") ?></td>
    </tr>
  </table>
<?php }) ?>

<?= $this->partial("footer") ?>
