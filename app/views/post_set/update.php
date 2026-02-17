<div id="post-set-update">
  <h2>Edit Post Set</h2>

  <?= $this->formTag(['action' => 'update', 'id' => $this->post_set->id], ['method' => 'post'], function() { ?>
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>

    <table>
      <tr>
        <td>Name</td>
        <td><?= $this->textFieldTag('post_set[name]', $this->h($this->post_set->name), ['size' => 50]) ?></td>
      </tr>
      <tr>
        <td>Shortname</td>
        <td><?= $this->textFieldTag('post_set[shortname]', $this->h($this->post_set->shortname), ['size' => 50]) ?></td>
      </tr>
      <tr>
        <td>Description</td>
        <td><?= $this->textAreaTag('post_set[description]', $this->h($this->post_set->description), ['rows' => 6, 'cols' => 60]) ?></td>
      </tr>
      <tr>
        <td>Public</td>
        <td>
          <?= $this->hiddenFieldTag('post_set[is_public]', '0') ?>
          <label><?= $this->checkBoxTag('post_set[is_public]', '1', (bool)$this->post_set->is_public) ?> Visible to everyone</label>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <?= $this->submitTag('Save Changes') ?>
          <?= $this->linkTo('Back', ['action' => 'show', 'id' => $this->post_set->id]) ?>
        </td>
      </tr>
    </table>
  <?php }) ?>

  <div style="margin-top: 1.5em;">
    <?= $this->formTag(['action' => 'destroy', 'id' => $this->post_set->id], ['method' => 'post'], function() { ?>
      <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
      <?= $this->submitTag('Delete Set') ?>
    <?php }) ?>
  </div>
</div>
