<div class="section">
  <h6><?= $this->t('user_blocked') ?></h6>
  <?= $this->formTag(['action' => 'unblock'], function(){ ?>
    <table width="100%" class="highlightable">
      <thead>
        <tr>
          <th width="1%"></th>
          <th width="19%"><?= $this->t('user_user') ?></th>
          <th width="10%"><?= $this->t('user_expires') ?></th>
          <th width="15%"><?= $this->t('user_when') ?></th>
          <th width="60%"><?= $this->t('user_reason') ?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <td colspan="4"><?= $this->submitTag($this->t('user_unblock')) ?></td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->users as $user) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->checkBoxTag("user[".$user->id."]") ?></td>
            <td><?= $this->linkTo($this->h($user->pretty_name()), ['user#show', 'id' => $user->id]) ?></td>
            <td><?= $this->timeAgoInWords($user->ban->expires_at) ?></td>
            <td><?= $user->ban->expires_at ?></td>
            <td><?= $this->h($user->ban->reason) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php }) ?>

  <h6><?= $this->t('user_blocked_ips') ?></h6>
  <?= $this->formTag('blocks#unblock_ip', function() { ?>
    <table width="100%" class="highlightable">
      <thead>
        <tr>
          <th width="1%"></th>
          <th width="19%"><?= $this->t('user_ip') ?></th>
          <th width="10%"><?= $this->t('user_expires') ?></th>
          <th width="10%"><?= $this->t('user_banned_by') ?></th>
          <th width="60%"><?= $this->t('user_reason') ?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <td colspan="4"><?= $this->submitTag($this->t('user_unblock')) ?></td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->ip_bans as $ban) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->checkBoxTag("ip_ban[".$ban->id."]") ?></td>
            <td><?= $this->h($ban->ip_addr) ?></td>
            <td><?= $ban->expires_at ? $this->timeAgoInWords($ban->expires_at) : "never" ?></td>
            <td><?= $this->linkTo($this->h($ban->user->pretty_name()), ['user#show', 'id' => $ban->user->id]) ?></td>
            <td><?= $this->h($ban->reason) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php }) ?>
  <?= $this->formTag('blocks#block_ip', function(){ ?>
    <table class="form">
      <tfoot>
        <tr>
          <td></td>
          <td><?= $this->submitTag($this->t('user_resend_submit')) ?></td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <th><label for="ip_addr"><?= $this->t('user_address') ?></label>
            <p><?= "IP masks <strong>not</strong> yet supported" //$this->t('user_address_text') ?></p></th>
          <td><?=$this->textField("ban", "ip_addr", ['size' => '40']) ?></td>
        </tr>
        <tr>
          <th><label for="ban_reason"><?= $this->t('user_reason') ?></label></th>
          <td><?= $this->textArea("ban", "reason", ['size' => '40x5']) ?></td>
        </tr>
        <tr>
          <th>
            <label for="ban_duration"><?= $this->t('user_duration') ?></label>
          </th>
          <td><?=$this->textField("ban", "duration", ['size' => '10', 'required']) ?></td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>

<?= $this->partial("footer") ?>
