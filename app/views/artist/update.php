<div id="artist-update">
  <p><?= $this->t('artist.create.info')?></p>

  <div id="preview" style="display: none; margin: 1em 0;">
  </div>

  <?= $this->formTag(['action' => "update"], ['level' => 'member'], function () { ?>
    <?= $this->hiddenFieldTag("id", $this->artist->id) ?>

    <table class="form">
      <tr>
        <th><label for="artist_name"><?= $this->t('artist.create.name') ?></label></th>
        <td><?= $this->textField('artist', 'name', ['size' => 80]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_aliases"><?= $this->t('artist.create.aliases') ?></label></th>
        <td><?= $this->textField('artist', 'alias_names', ['size' => 80]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_members"><?= $this->t('artist.create.members') ?></label></th>
        <td><?= $this->textField('artist', 'member_names', ['size' => 80]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_urls"><?= $this->t('artist.create.urls') ?></label></th>
        <td><?= $this->textArea('artist', 'urls', ['size' => "80x6", 'class' => "no-block"]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_notes"><?= $this->t('artist.create.notes') ?></label></th>
        <td><?= $this->textArea('artist', 'notes', ['size' => "80x6", 'class' => "no-block", 'disabled' => $this->artist->notes_locked()]) ?></td>
      </tr>
      <tr>
        <td colspan="2">
          <?= $this->submitTag($this->t('buttons.save')) ?>
          <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()") ?>
          <input name="preview" onclick="new Ajax.Updater('preview', '/artist/preview', {asynchronous:true, evalScripts:true, method:'get', onSuccess:function(request){$('preview').show()}, parameters:Form.serialize(this.form)});" type="button" value="Preview Notes"/>
        </td>
      </tr>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>
