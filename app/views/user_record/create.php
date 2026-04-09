<h4><?= $this->t('record_add') ?><?= $this->h($this->user->pretty_name()) ?></h4>

<?= $this->formTag(function(){ ?>
  <?= $this->hiddenFieldTag("user_id", $this->user->id) ?>
  <table width="100%">
    <tbody>
      <tr>
        <th width="10%"><label><?= $this->t('record_category') ?? 'Category' ?></label></th>
        <td width="90%">
          <select name="user_record[category]">
            <option value="negative">Negative</option>
            <option value="positive">Positive</option>
            <option value="neutral">Neutral</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label><?= $this->t('record_reason') ?></label></th>
        <td><?= $this->textArea('user_record', 'body', ['size' => "20x8"]) ?></td>
      </tr>
      <tr>
        <th><label>Send Dmail</label></th>
        <td><?= $this->checkBoxTag("send_dmail", "1", false) ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('record_submit')) ?> <?= $this->buttonToFunction($this->t('record_cancel'), "location.back()") ?></td>
      </tr>
    </tfoot>
  </table>
<?php }) ?>
