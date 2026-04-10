<div>
  <h4><?= $this->t('.title') ?></h4>

  <?= $this->formTag(function () { ?>
    <table class="form">
      <tfoot>
        <tr>
          <td colspan="2">
            <?= $this->submitTag($this->t('.reset')) ?>
          </td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <th width="15%"><label class="block" for="user_name"><?= $this->t('.name') ?></label></th>
          <td width="85%">
            <?= $this->textField('user', 'name', ['class' => 'ac-user-name']) ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>
