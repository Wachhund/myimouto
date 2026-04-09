<?php $this->provide('title', 'API Keys') ?>
<h1>API Keys</h1>

<?php if (!empty($this->new_raw_key)) : ?>
  <div class="status-notice" style="border: 2px solid #c00; background: #ffe0e0; padding: 1em; margin-bottom: 1em;">
    <strong>Your new API key (copy it now -- it will not be shown again):</strong>
    <pre style="margin: 0.5em 0; padding: 0.5em; background: #fff; border: 1px solid #ccc; word-break: break-all; font-size: 1.1em;"><?= $this->h($this->new_raw_key) ?></pre>
  </div>
<?php endif ?>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th>Name</th>
      <th>Created</th>
      <th>Expires</th>
      <th>Last Used</th>
      <th>Last IP</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (count($this->api_keys) === 0) : ?>
      <tr>
        <td colspan="6" style="text-align: center; padding: 1em;">No API keys yet.</td>
      </tr>
    <?php else : ?>
      <?php foreach ($this->api_keys as $key) : ?>
        <tr>
          <td><?= $this->h($key->name) ?></td>
          <td><?= $this->h($key->created_at) ?></td>
          <td><?= $key->expires_at ? $this->h($key->expires_at) : 'Never' ?></td>
          <td><?= $key->last_used_at ? $this->h($key->last_used_at) : 'Never' ?></td>
          <td><?= $key->last_ip_address ? $this->h($key->last_ip_address) : '-' ?></td>
          <td>
            <?= $this->formTag(['api_key#regenerate', 'id' => $key->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 4px;'], function() { ?>
              <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
              <input type="submit" value="Regenerate" onclick="return confirm('Regenerate this key? The old key will stop working immediately.');" />
            <?php }) ?>
            <?= $this->formTag(['api_key#destroy', 'id' => $key->id], ['method' => 'post', 'style' => 'display:inline-block;'], function() { ?>
              <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
              <input type="submit" value="Delete" onclick="return confirm('Delete this key? This cannot be undone.');" />
            <?php }) ?>
          </td>
        </tr>
      <?php endforeach ?>
    <?php endif ?>
  </tbody>
</table>

<h4 style="margin-top: 1.5em;">Create New API Key</h4>
<p>You can have up to <?= (int)$this->max_keys ?> keys. Currently using <?= count($this->api_keys) ?>.</p>

<?= $this->formTag(['api_key#create'], ['method' => 'post'], function() { ?>
  <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
  <table class="form">
    <tr>
      <th><label for="api_key_name">Name</label></th>
      <td><input type="text" id="api_key_name" name="api_key[name]" size="30" maxlength="100" required="required" placeholder="e.g. My Script" /></td>
    </tr>
    <tr>
      <th><label for="api_key_expires_at">Expires (optional)</label></th>
      <td><input type="date" id="api_key_expires_at" name="api_key[expires_at]" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" /></td>
    </tr>
    <tr>
      <td colspan="2"><input type="submit" value="Create Key" /></td>
    </tr>
  </table>
<?php }) ?>
