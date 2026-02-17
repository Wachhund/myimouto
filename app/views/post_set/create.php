<div id="post-set-create">
  <h2>Create Post Set</h2>

  <?= $this->formTag(['action' => 'create'], ['method' => 'post'], function() { ?>
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
          <label><?= $this->checkBoxTag('post_set[is_public]', '1', true) ?> Visible to everyone</label>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <?= $this->submitTag('Create Set') ?>
          <?= $this->linkTo('Cancel', ['action' => 'index']) ?>
        </td>
      </tr>
    </table>
  <?php }) ?>
</div>
