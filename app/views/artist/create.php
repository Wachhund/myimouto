<div id="artist-create">
  <p><?= $this->t('.info') ?></p>

  <?= $this->formTag(['action' => "create"], ['level' => 'member'], function () { ?>
    <table class="form">
      <tr>
        <th><label for="artist_name"><?= $this->t('.name') ?></label></th>
        <td><?= $this->textField('artist', 'name', ['size' => 80]) ?></td>
      </tr>
      <?php if ($this->params()->alias_id) : ?>
        <tr>
          <th><label for="artist_alias_name"><?= $this->t('.alias_for') ?></label></th>
          <td><?= $this->textField('artist', 'alias_name', ['size' => 80]) ?></td>
        </tr>
      <?php endif ?>
      <tr>
        <th><label for="artist_alias_names"><?= $this->t('.aliases') ?></label</th>
        <td><?= $this->textField('artist', 'alias_names', ['size' => 80, 'value' => $this->params()->jp_name]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_member_names"><?= $this->t('.members') ?></label></th>
        <td><?= $this->textField('artist', 'member_names', ['size' => 80]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_urls"><?= $this->t('.urls') ?></label></th>
        <td><?= $this->textArea('artist', 'urls', ['size' => "80x6", 'class' => "no-block"]) ?></td>
      </tr>
      <tr>
        <th><label for="artist_notes"><?= $this->t('.notes') ?></label></th>
        <td><?= $this->textArea('artist', 'notes', ['size' => "80x6", 'class' => "no-block"]) ?></td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('buttons.save')) ?> <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()") ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>
