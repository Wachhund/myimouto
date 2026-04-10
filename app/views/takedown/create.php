<div id="takedown-create">
  <h4>Create Takedown</h4>

  <?= $this->formTag(['action' => 'create'], ['method' => 'post'], function () { ?>
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>

    <table>
      <tr>
        <td width="15%"><label>Email</label></td>
        <td><?= $this->textFieldTag('takedown[email]', '', ['size' => 50, 'placeholder' => 'Contact email of the requester']) ?></td>
      </tr>
      <tr>
        <td><label>Source</label></td>
        <td><?= $this->textAreaTag('takedown[source]', '', ['rows' => 3, 'cols' => 60, 'placeholder' => 'Original source URLs or references']) ?></td>
      </tr>
      <tr>
        <td><label>Reason</label></td>
        <td><?= $this->textAreaTag('takedown[reason]', '', ['rows' => 6, 'cols' => 60, 'placeholder' => 'Reason for the takedown request']) ?></td>
      </tr>
      <tr>
        <td><label>Instructions</label></td>
        <td><?= $this->textAreaTag('takedown[instructions]', '', ['rows' => 3, 'cols' => 60, 'placeholder' => 'Instructions visible to the requester']) ?></td>
      </tr>
      <tr>
        <td><label>Staff Notes</label></td>
        <td><?= $this->textAreaTag('takedown[notes]', '', ['rows' => 3, 'cols' => 60, 'placeholder' => 'Internal notes (not visible to requester)']) ?></td>
      </tr>
      <tr>
        <td><label>Post IDs</label></td>
        <td><?= $this->textFieldTag('takedown[post_ids]', '', ['size' => 50, 'placeholder' => 'Space or comma separated post IDs']) ?></td>
      </tr>
      <tr>
        <td colspan="2">
          <?= $this->submitTag('Create Takedown') ?>
          <?= $this->linkTo('Cancel', ['action' => 'index']) ?>
        </td>
      </tr>
    </table>
  <?php }) ?>
</div>
