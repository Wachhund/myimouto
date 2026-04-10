<div class="page">
  <p><?= $this->t('user_resend') ?></p>
  <?= $this->formTag('#resend_confirmation', function () { ?>
    <table class="form">
      <tbody>
        <tr>
          <th>
            <label class="block" for="email"><?= $this->t('user_resend_email') ?></label>
          </th>
          <td>
            <?= $this->textFieldTag("email") ?>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">
            <?= $this->submitTag($this->t('user_resend_submit')) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>
