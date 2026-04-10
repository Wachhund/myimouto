<div id="takedown-status">
  <h4>Takedown Status Check</h4>

  <p>Enter your verification code to check the status of a takedown request.</p>

  <?= $this->formTag(['takedown#status'], ['method' => 'get'], function () { ?>
    <label for="vericode">Verification Code:</label><br/>
    <?= $this->textFieldTag('vericode', $this->h($this->params()->vericode), ['size' => 40, 'placeholder' => 'e.g. a1b2c3d4e5f6...', 'id' => 'vericode']) ?>
    <?= $this->submitTag('Check Status') ?>
  <?php }) ?>

  <?php if (isset($this->notice_message) && $this->notice_message) : ?>
    <div style="margin-top: 1em; padding: 0.5em; background: #fee; border: 1px solid #c00;">
      <?= $this->h($this->notice_message) ?>
    </div>
  <?php endif ?>

  <?php if ($this->takedown) : ?>
    <div style="margin-top: 1.5em;">
      <table width="50%">
        <tbody>
          <tr>
            <th width="30%">Takedown ID</th>
            <td>#<?= (int) $this->takedown->id ?></td>
          </tr>
          <tr>
            <th>Status</th>
            <td><strong><?= $this->h($this->takedown->status_label()) ?></strong></td>
          </tr>
          <?php if ($this->takedown->instructions) : ?>
            <tr>
              <th>Instructions</th>
              <td><?= nl2br($this->h($this->takedown->instructions)) ?></td>
            </tr>
          <?php endif ?>
          <tr>
            <th>Created</th>
            <td><?= $this->h($this->takedown->created_at) ?></td>
          </tr>
          <?php if ($this->takedown->updated_at) : ?>
            <tr>
              <th>Updated</th>
              <td><?= $this->h($this->takedown->updated_at) ?></td>
            </tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
