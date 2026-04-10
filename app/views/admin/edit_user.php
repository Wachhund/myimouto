<div id="admin-edit-user">
  <?= $this->formTag('#edit_user', function () { ?>
    <table class="form">
      <tr>
        <th width="15%"><label class="block" for="user_name"><?= $this->t('.user') ?></label></th>
        <td width="85%">
          <?= $this->textField('user', 'name', ['class' => 'ac-user-name']) ?>
        </td>
      </tr>
      <tr>
        <th>
          <label class="block" for="user_level"><?= $this->t('.level') ?></label>
        </th>
        <td>
          <?= $this->select('user', 'level', array_filter(array_map(function ($x) {
              if ($x > CONFIG()->user_levels['Blocked']) {
                  return $x;
              }
          }, CONFIG()->user_levels))) ?>
        </td>
      </tr>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('.save')) ?></td>
      </tr>
    </table>
  <?php }) ?>
</div>
